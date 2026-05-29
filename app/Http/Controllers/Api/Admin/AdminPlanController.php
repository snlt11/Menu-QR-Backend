<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StorePlanRequest;
use App\Http\Requests\Api\Admin\UpdatePlanRequest;
use App\Models\Plan;
use App\Models\TenantSubscription;
use Illuminate\Http\JsonResponse;

class AdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $plans->map(fn (Plan $plan) => $this->formatPlan($plan)),
        ]);
    }

    public function active(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $plans->map(fn (Plan $plan) => $this->formatPlan($plan)),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $plan = Plan::create($data);

        return response()->json([
            'status' => 201,
            'message' => 'Plan created successfully.',
            'data' => $this->formatPlan($plan),
        ], 201);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json([
            'status' => 200,
            'data' => $this->formatPlan($plan),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return response()->json([
            'status' => 200,
            'message' => 'Plan updated successfully.',
            'data' => $this->formatPlan($plan->fresh()),
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $activeCount = TenantSubscription::where('plan_id', $plan->id)
            ->whereIn('status', ['trialing', 'active'])
            ->count();

        if ($activeCount > 0) {
            return response()->json([
                'status' => 422,
                'message' => "Cannot delete plan \"{$plan->name}\". It is currently used by {$activeCount} active/trialing subscription(s). Disable it instead.",
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Plan deleted successfully.',
        ]);
    }

    private function formatPlan(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'billing_cycle' => $plan->billing_cycle,
            'trial_days' => $plan->trial_days,
            'max_owners' => $plan->max_owners,
            'max_staff' => $plan->max_staff,
            'max_kitchen' => $plan->max_kitchen,
            'features' => $plan->features,
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
