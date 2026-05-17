<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ShopProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = DB::table('shop_profile')->first();
        $settings = DB::table('shop_settings')->first();

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

        DB::table('shop_profile')->update($data + ['updated_at' => now()]);

        return $this->show();
    }
}
