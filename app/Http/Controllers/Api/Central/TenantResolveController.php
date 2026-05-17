<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantResolveController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['slug' => ['required', 'string', 'max:255']]);

        $tenant = Tenant::where('slug', $request->string('slug'))->first();

        if (! $tenant || $tenant->status !== 'active') {
            return response()->json([
                'status' => 404,
                'message' => 'Shop not found. Please check your shop name.',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'login_url' => "/t/{$tenant->slug}/login",
            ],
        ]);
    }
}
