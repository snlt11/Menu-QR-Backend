<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('users')
            ->select('id', 'central_user_id', 'name', 'email', 'phone', 'role', 'status', 'created_at', 'updated_at')
            ->orderBy('created_at')
            ->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:manager,cashier,kitchen'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $id = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $id,
            'central_user_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 201,
            'data' => DB::table('users')
                ->select('id', 'central_user_id', 'name', 'email', 'phone', 'role', 'status', 'created_at', 'updated_at')
                ->where('id', $id)
                ->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('users')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('users')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'role' => ['sometimes', 'string', 'in:owner,manager,cashier,kitchen'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        DB::table('users')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('users')->where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('users')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        DB::table('users')->where('id', $id)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }
}
