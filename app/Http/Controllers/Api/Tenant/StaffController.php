<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('shop_users')->orderBy('created_at')->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('shop_users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'in:owner,manager,cashier,kitchen'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $centralUserId = User::on('central')->where('email', $data['email'])->value('id');
        if (! $centralUserId) {
            return response()->json([
                'status' => 422,
                'message' => 'No central user with that email — register them first.',
            ], 422);
        }

        $id = (string) Str::uuid();
        DB::table('shop_users')->insert([
            'id' => $id,
            'central_user_id' => $centralUserId,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 201,
            'data' => DB::table('shop_users')->where('id', $id)->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('shop_users')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('shop_users')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('shop_users', 'email')->ignore($id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'role' => ['sometimes', 'string', 'in:owner,manager,cashier,kitchen'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        DB::table('shop_users')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('shop_users')->where('id', $id)->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('shop_users')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        DB::table('shop_users')->where('id', $id)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }
}
