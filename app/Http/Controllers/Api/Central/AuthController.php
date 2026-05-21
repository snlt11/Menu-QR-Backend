<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Central\LoginRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Tenant login. Auths against tenant.users only — never central.
     */
    public function login(LoginRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();
        if (! $tenant || $tenant->status !== 'active') {
            return response()->json([
                'status' => 404,
                'message' => 'Shop not found or inactive.',
            ], 404);
        }

        [$tenantUser, $token] = $tenant->run(function () use ($request, $tenant) {
            $tenantUser = TenantUser::where('email', $request->string('email'))->first();
            if (! $tenantUser || ! Hash::check($request->string('password'), $tenantUser->password)) {
                return [null, null];
            }
            if ($tenantUser->status !== 'active') {
                return [null, 'inactive'];
            }
            $token = $tenantUser->createToken(
                "tenant:{$tenant->slug}",
                [$tenantUser->role],
            )->plainTextToken;
            return [$tenantUser, $token];
        });

        if (! $tenantUser) {
            if ($token === 'inactive') {
                return response()->json([
                    'status' => 403,
                    'message' => 'Your account is inactive. Contact the shop owner.',
                ], 403);
            }
            return response()->json([
                'status' => 401,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Login successful',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'user' => [
                    'id' => $tenantUser->id,
                    'name' => $tenantUser->name,
                    'email' => $tenantUser->email,
                    'role' => $tenantUser->role,
                    'status' => $tenantUser->status,
                ],
                'token' => $token,
                'redirect_url' => $this->redirectFor($tenant->slug, $tenantUser->role),
            ],
        ]);
    }

    private function redirectFor(string $slug, string $role): string
    {
        return match ($role) {
            'owner', 'manager' => "/t/{$slug}/dashboard",
            'cashier' => "/t/{$slug}/cashier",
            'kitchen' => "/t/{$slug}/kitchen",
            default => "/t/{$slug}/dashboard",
        };
    }
}
