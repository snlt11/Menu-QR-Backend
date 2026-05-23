<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = DB::table('profile')->first();
        $settings = DB::table('settings')->first();

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

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'service_charge_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'opening_hours' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        DB::table('profile')->update($data + ['updated_at' => now()]);

        return $this->show();
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'points_enabled' => ['sometimes', 'boolean'],
            'earn_rate_amount' => ['sometimes', 'integer', 'min:1'],
            'earn_rate_points' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_points' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_amount' => ['sometimes', 'integer', 'min:1'],
            'table_session_enabled' => ['sometimes', 'boolean'],
            'table_session_expiry_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
        ]);

        DB::table('settings')->update($data + ['updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('settings')->first(),
        ]);
    }
}
