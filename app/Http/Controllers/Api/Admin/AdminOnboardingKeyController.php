<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class AdminOnboardingKeyController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    public function generate(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        try {
            $plainKey = $this->onboardingService->generateKey($tenant);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not generate onboarding key. Please try again.',
            ], 500);
        }

        $tenant->refresh();

        return response()->json([
            'message' => 'Onboarding key generated.',
            'data' => [
                'onboarding_key' => $plainKey,
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'onboarding_status' => $tenant->onboarding_status,
                ],
            ],
        ]);
    }

    public function regenerate(string $id): JsonResponse
    {
        return $this->generate($id);
    }
}
