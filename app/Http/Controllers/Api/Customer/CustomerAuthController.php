<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\LoginCustomerRequest;
use App\Http\Requests\Api\Customer\RegisterCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController extends Controller
{
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
        ]);

        CustomerProfile::create([
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'total_points' => 0,
            'membership_level' => 'basic',
        ]);

        $token = $customer->createToken('customer', ['customer'])->plainTextToken;

        return response()->json([
            'status' => 201,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(LoginCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $query = Customer::where('status', 'active');

        if (! empty($data['email'])) {
            $query->where('email', $data['email']);
        } else {
            $query->where('phone', $data['phone']);
        }

        $customer = $query->first();

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            return response()->json(['status' => 401, 'message' => 'Invalid credentials.'], 401);
        }

        $token = $customer->createToken('customer', ['customer'])->plainTextToken;

        CustomerProfile::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'total_points' => 0,
                'membership_level' => 'basic',
            ],
        );

        return response()->json([
            'status' => 200,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();

        $profile = CustomerProfile::where('customer_id', $customer->id)->first();

        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'points_balance' => $profile ? (int) $profile->total_points : 0,
                'customer_type' => $profile ? $profile->membership_level : 'basic',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 200, 'message' => 'Logged out.']);
    }
}
