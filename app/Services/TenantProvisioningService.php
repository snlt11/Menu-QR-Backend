<?php

namespace App\Services;

use App\Actions\CreateTenantAction;
use App\Models\Tenant;
use App\Models\TenantRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function approveTenantRequest(TenantRequest $request): Tenant
    {
        if (! $request->isPending()) {
            throw new \LogicException('Only pending requests can be approved.');
        }

        $conflict = Tenant::where('slug', $request->requested_slug)->exists();
        if ($conflict) {
            throw new \RuntimeException('A tenant with this slug already exists.');
        }

        return DB::transaction(function () use ($request): Tenant {
            $owner = [
                'name' => $request->owner_name,
                'email' => $request->owner_email,
                'phone' => $request->owner_phone,
                'password' => $request->password ?? bcrypt(Str::random(32)),
            ];

            $tenant = app(CreateTenantAction::class)->execute(
                $request->shop_name,
                $request->requested_slug,
                $owner,
            );

            $request->update([
                'status' => 'approved',
                'tenant_id' => $tenant->id,
                'approved_at' => now(),
            ]);

            return $tenant;
        });
    }
}
