<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateSubscriptionRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private readonly TenantSubscriptionService $subscriptionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()->where('status', 'active')->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('owner_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status === 'expiring_soon') {
                $query->whereHas('currentSubscription', function ($q) {
                    $q->where('status', 'trialing')
                        ->where('trial_ends_at', '<=', now()->addDays(3))
                        ->where('trial_ends_at', '>', now());
                });
            } elseif ($status === 'none') {
                $query->whereDoesntHave('currentSubscription');
            } else {
                $query->whereHas('currentSubscription', function ($q) use ($status) {
                    $q->where('status', $status);
                });
            }
        }

        if ($planSlug = $request->query('plan')) {
            $query->whereHas('currentSubscription.plan', function ($q) use ($planSlug) {
                $q->where('slug', $planSlug);
            });
        }

        $page = (int) ($request->query('page', 1));
        $perPage = (int) ($request->query('per_page', 20));

        $tenants = $query->with('currentSubscription.plan')->paginate($perPage, ['*'], 'page', $page);

        $rows = $tenants->map(fn (Tenant $tenant) => $this->formatRow($tenant));

        return response()->json([
            'status' => 200,
            'data' => $rows,
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load('currentSubscription.plan');

        $subscription = $tenant->currentSubscription;

        $userCounts = $this->getUserCounts($tenant);

        return response()->json([
            'status' => 200,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'owner_name' => $tenant->owner_name,
                    'owner_email' => $tenant->owner_email,
                ],
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'plan' => $subscription->plan ? [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                        'price' => $subscription->plan->price,
                        'billing_cycle' => $subscription->plan->billing_cycle,
                        'max_owners' => $subscription->plan->max_owners,
                        'max_staff' => $subscription->plan->max_staff,
                        'max_kitchen' => $subscription->plan->max_kitchen,
                        'features' => $subscription->plan->features,
                    ] : null,
                    'status' => $subscription->status,
                    'starts_at' => $subscription->starts_at?->toIso8601String(),
                    'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                    'current_period_starts_at' => $subscription->current_period_starts_at?->toIso8601String(),
                    'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
                    'days_left' => $subscription->getDaysLeft(),
                    'metadata' => $subscription->metadata,
                    'created_at' => $subscription->created_at?->toIso8601String(),
                ] : null,
                'user_counts' => $userCounts,
            ],
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validated();
        $data['changed_by'] = $request->user()->id ?? null;

        $subscription = $this->subscriptionService->updateSubscription($tenant, $data);

        if (! $subscription) {
            if (isset($data['plan_id'])) {
                $plan = Plan::find($data['plan_id']);
                if ($plan) {
                    $subscription = $this->subscriptionService->assignPlan($tenant, $plan, $data);
                }
            }

            if (! $subscription) {
                return response()->json([
                    'status' => 422,
                    'message' => 'No subscription found and no plan specified.',
                ], 422);
            }
        }

        $tenant->load('currentSubscription.plan');

        return response()->json([
            'status' => 200,
            'message' => 'Subscription updated successfully.',
            'data' => $this->formatRow($tenant),
        ]);
    }

    private function formatRow(Tenant $tenant): array
    {
        $subscription = $tenant->currentSubscription;
        $plan = $subscription?->plan;
        $userCounts = $this->getUserCounts($tenant);

        return [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'owner_name' => $tenant->owner_name,
            'owner_email' => $tenant->owner_email,
            'plan_name' => $plan?->name,
            'plan_slug' => $plan?->slug,
            'plan_price' => $plan?->price,
            'plan_billing_cycle' => $plan?->billing_cycle,
            'subscription_status' => $subscription?->status,
            'starts_at' => $subscription?->starts_at?->toIso8601String(),
            'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
            'current_period_starts_at' => $subscription?->current_period_starts_at?->toIso8601String(),
            'current_period_ends_at' => $subscription?->current_period_ends_at?->toIso8601String(),
            'days_left' => $subscription?->getDaysLeft(),
            'owner_count' => $userCounts['owner'],
            'staff_count' => $userCounts['staff'],
            'kitchen_count' => $userCounts['kitchen'],
            'created_at' => $tenant->created_at?->toIso8601String(),
        ];
    }

    private function getUserCounts(Tenant $tenant): array
    {
        try {
            return $tenant->run(function () {
                return [
                    'owner' => \DB::table('users')->where('role', 'owner')->where('status', 'active')->count(),
                    'staff' => \DB::table('users')->whereIn('role', ['staff', 'manager', 'cashier'])->where('status', 'active')->count(),
                    'kitchen' => \DB::table('users')->where('role', 'kitchen')->where('status', 'active')->count(),
                ];
            });
        } catch (\Throwable) {
            return ['owner' => 0, 'staff' => 0, 'kitchen' => 0];
        }
    }
}
