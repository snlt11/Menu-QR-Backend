<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\StoreStaffRequest;
use App\Http\Requests\Api\Tenant\UpdateStaffRequest;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;

class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = TenantUser::select('id', 'name', 'email', 'phone', 'role', 'status', 'created_at', 'updated_at')
            ->oldest()
            ->get();

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->validated();

        $staff = TenantUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json([
            'status' => 201,
            'data' => TenantUser::select('id', 'name', 'email', 'phone', 'role', 'status', 'created_at', 'updated_at')
                ->where('id', $staff->id)
                ->first(),
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = TenantUser::select('id', 'name', 'email', 'phone', 'role', 'status', 'created_at', 'updated_at')
            ->where('id', $id)
            ->first();

        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $row]);
    }

    public function update(UpdateStaffRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = TenantUser::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        $row->update($request->validated());

        return response()->json([
            'status' => 200,
            'data' => TenantUser::select('id', 'name', 'email', 'phone', 'role', 'status', 'created_at', 'updated_at')
                ->where('id', $id)
                ->first(),
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = TenantUser::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Staff not found.'], 404);
        }

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }
}
