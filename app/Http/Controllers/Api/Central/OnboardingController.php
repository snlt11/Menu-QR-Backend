<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    public function verify(Request $request, OnboardingService $service): JsonResponse
    {
        $request->validate([
            'onboarding_key' => ['required', 'string'],
        ]);

        try {
            $tenant = $service->verifyKey($request->input('onboarding_key'));
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

    public function complete(Request $request, OnboardingService $service): JsonResponse
    {
        $request->validate([
            'onboarding_key' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $tenant = $service->completeOnboarding(
                $request->input('onboarding_key'),
                $request->input('password'),
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
