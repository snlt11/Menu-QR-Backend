<?php

namespace App\Services;

use App\Actions\CreateTenantAction;
use App\Models\Tenant;
use App\Models\TenantRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function __construct(
        private readonly CreateTenantAction $createTenantAction,
        private readonly OnboardingService $onboardingService,
        private readonly TenantSubscriptionService $subscriptionService,
    ) {}

    public function approveTenantRequest(TenantRequest $request): array
    {
        if (! $request->isPending()) {
            throw new \LogicException('Only pending requests can be approved.');
        }

        $conflict = Tenant::where('slug', $request->requested_slug)->exists();
        if ($conflict) {
            throw new \RuntimeException('A tenant with this slug already exists.');
        }

        return DB::transaction(function () use ($request): array {
            $owner = [
                'name' => $request->owner_name,
                'email' => $request->owner_email,
                'phone' => $request->owner_phone,
                'password' => $request->password ?? bcrypt(Str::random(32)),
            ];

            $tenant = $this->createTenantAction->execute(
                $request->shop_name,
                $request->requested_slug,
                $owner,
            );

            $request->update([
                'status' => 'approved',
                'tenant_id' => $tenant->id,
                'approved_at' => now(),
            ]);

            $plainKey = $this->onboardingService->generateKey($tenant);

            $this->subscriptionService->startFreeTrial($tenant);

            return ['tenant' => $tenant, 'onboarding_key' => $plainKey];
        });
    }
}
