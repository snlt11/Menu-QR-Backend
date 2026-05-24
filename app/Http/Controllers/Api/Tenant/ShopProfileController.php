<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\UpdateSettingsRequest;
use App\Http\Requests\Api\Tenant\UpdateShopProfileRequest;
use App\Models\Profile;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;

class ShopProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = Profile::first();
        $settings = Settings::first();

        return response()->json([
            'status' => 200,
            'data' => [
                'profile' => $profile,
                'settings' => $settings,
                'tenant' => [
                    'id' => tenant('id'),
                    'slug' => tenant('slug'),
                    'name' => tenant('name'),
                ],
            ],
        ]);
    }

    public function update(UpdateShopProfileRequest $request): JsonResponse
    {
        $profile = Profile::first();
        $profile->update($request->validated());

        return $this->show();
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = Settings::first();
        $settings->update($request->validated());

        return response()->json([
            'status' => 200,
            'data' => Settings::first(),
        ]);
    }
}
