<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Central\CompleteOnboardingRequest;
use App\Http\Requests\Api\Central\VerifyOnboardingKeyRequest;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    public function verify(VerifyOnboardingKeyRequest $request, OnboardingService $service): JsonResponse
    {
        try {
            $tenant = $service->verifyKey($request->validated('onboarding_key'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Invalid or expired onboarding key.',
            ], 422);
        }

        return response()->json([
            'message' => 'Onboarding key verified.',
            'data' => [
                'tenant_id' => $tenant->id,
                'shop_name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ]);
    }

    public function complete(CompleteOnboardingRequest $request, OnboardingService $service): JsonResponse
    {
        try {
            $tenant = $service->completeOnboarding(
                $request->validated('onboarding_key'),
                $request->validated('password'),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not complete onboarding. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Onboarding completed.',
            'data' => [
                'tenant' => [
                    'slug' => $tenant->slug,
                ],
                'login_url' => "/t/{$tenant->slug}/login",
            ],
        ]);
    }
}
