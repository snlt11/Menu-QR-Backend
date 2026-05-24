<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\RejectTenantRequest;
use App\Models\TenantRequest;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantRequestController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioningService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = TenantRequest::query()->latest();

        if ($request->query('status') && in_array($request->query('status'), ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->query('status'));
        }

        $requests = $query->get()->map(fn (TenantRequest $r) => $this->formatItem($r));

        return response()->json([
            'status' => 200,
            'data' => $requests,
        ]);
    }

    public function show(TenantRequest $tenantRequest): JsonResponse
    {
        $tenantRequest->load('tenant');

        return response()->json([
            'status' => 200,
            'data' => $this->formatItem($tenantRequest, true),
        ]);
    }

    public function approve(TenantRequest $tenantRequest): JsonResponse
    {
        if (! $tenantRequest->isPending()) {
            return response()->json([
                'status' => 422,
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        try {
            $result = $this->provisioningService->approveTenantRequest($tenantRequest);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 409,
                'message' => $e->getMessage(),
            ], 409);
        }

        $tenant = $result['tenant'];

        return response()->json([
            'status' => 200,
            'message' => 'Request approved. Tenant has been created.',
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'onboarding_key' => $result['onboarding_key'],
            ],
        ]);
    }

    public function reject(RejectTenantRequest $request, TenantRequest $tenantRequest): JsonResponse
    {
        if (! $tenantRequest->isPending()) {
            return response()->json([
                'status' => 422,
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $data = $request->validated();

        $tenantRequest->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Request rejected.',
        ]);
    }

    private function formatItem(TenantRequest $r, bool $full = false): array
    {
        $base = [
            'id' => $r->id,
            'shop_name' => $r->shop_name,
            'requested_slug' => $r->requested_slug,
            'owner_name' => $r->owner_name,
            'owner_email' => $r->owner_email,
            'owner_phone' => $r->owner_phone,
            'status' => $r->status,
            'created_at' => $r->created_at?->toIso8601String(),
        ];

        if ($full) {
            $base['approved_at'] = $r->approved_at?->toIso8601String();
            $base['rejected_at'] = $r->rejected_at?->toIso8601String();
            $base['rejection_reason'] = $r->rejection_reason;
            $base['tenant_id'] = $r->tenant_id;
            if ($r->tenant) {
                $base['tenant'] = [
                    'id' => $r->tenant->id,
                    'name' => $r->tenant->name,
                    'slug' => $r->tenant->slug,
                ];
            }
        }

        return $base;
    }
}
