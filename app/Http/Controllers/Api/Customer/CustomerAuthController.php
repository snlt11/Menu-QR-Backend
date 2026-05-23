<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CustomerAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('customers', 'email')],
            'password' => ['required', 'string', Password::min(6), 'confirmed'],
        ]);

        $customer = Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
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

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required_without:email', 'string', 'max:30'],
            'email' => ['required_without:phone', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

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

        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 200, 'message' => 'Logged out.']);
    }
}
