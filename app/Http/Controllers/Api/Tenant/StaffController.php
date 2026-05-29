<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\StoreStaffRequest;
use App\Http\Requests\Api\Tenant\UpdateStaffRequest;
use App\Models\TenantUser;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\JsonResponse;
use Stancl\Tenancy\Facades\Tenancy;

class StaffController extends Controller
{
    public function __construct(
        private readonly TenantSubscriptionService $subscriptionService,
    ) {}

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
        $role = $data['role'];

        $tenant = Tenancy::tenant();
        $roleLabel = match ($role) {
            'owner' => 'owner',
            'kitchen' => 'kitchen',
            default => 'staff',
        };

        if (! $this->subscriptionService->canCreateUser($tenant, $role)) {
            $limit = $this->subscriptionService->getRoleLimit($tenant, $role);

            return response()->json([
                'status' => 422,
                'message' => 'User limit reached for your current plan.',
                'errors' => [
                    'role' => ["Your current plan allows only {$limit} {$roleLabel} user".($limit === 1 ? '' : 's').'.'],
                ],
            ], 422);
        }

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
