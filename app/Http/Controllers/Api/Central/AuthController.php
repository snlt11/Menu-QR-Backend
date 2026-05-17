<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Central\LoginRequest;
use App\Http\Requests\Api\Central\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->input('phone'),
            'password' => $request->string('password'),
            'global_role' => $request->input('global_role', 'customer'),
            'status' => 'active',
        ]);

        $token = $user->createToken('central', ['central'])->plainTextToken;

        return response()->json([
            'status' => 201,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'global_role' => $user->global_role,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(LoginRequest $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->first();
        if (! $tenant || $tenant->status !== 'active') {
            return response()->json([
                'status' => 404,
                'message' => 'Shop not found or inactive.',
            ], 404);
        }

        $user = User::where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return response()->json([
                'status' => 401,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $shopUser = $tenant->run(function () use ($user) {
            return DB::table('users')
                ->where('central_user_id', $user->id)
                ->where('status', 'active')
                ->first();
        });

        if (! $shopUser) {
            return response()->json([
                'status' => 403,
                'message' => 'You do not have access to this shop.',
            ], 403);
        }

        $token = $user->createToken("tenant:{$tenant->slug}", [$shopUser->role])->plainTextToken;

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
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'shop_user' => [
                    'role' => $shopUser->role,
                    'status' => $shopUser->status,
                ],
                'token' => $token,
                'redirect_url' => $this->redirectFor($tenant->slug, $shopUser->role),
            ],
        ]);
    }

    private function redirectFor(string $slug, string $role): string
    {
        return match ($role) {
            'owner' => "/t/{$slug}/dashboard",
            'manager' => "/t/{$slug}/manager/dashboard",
            'cashier' => "/t/{$slug}/cashier/orders",
            'kitchen' => "/t/{$slug}/kitchen/orders",
            default => "/t/{$slug}/dashboard",
        };
    }
}
