<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SubscriptionHistory;
use App\Models\Tenant;
use App\Models\TenantSubscription;

class TenantSubscriptionService
{
    public function getCurrentSubscription(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::where('tenant_id', $tenant->id)->latest()->first();
    }

    public function startFreeTrial(Tenant $tenant): TenantSubscription
    {
        $existing = $this->getCurrentSubscription($tenant);
        if ($existing && in_array($existing->status, ['trialing', 'active'])) {
            return $existing;
        }

        $plan = Plan::where('slug', 'free-trial')->where('is_active', true)->first();

        if (! $plan) {
            throw new \RuntimeException('Free Trial plan not found.');
        }

        $trialDays = $plan->trial_days ?? 14;

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays($trialDays),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addDays($trialDays),
        ]);

        $this->writeHistory(
            $tenant->id,
            $subscription->id,
            action: 'started_trial',
            newPlanId: $plan->id,
            newStatus: 'trialing',
            note: 'Free trial started automatically on tenant approval.',
        );

        return $subscription;
    }

    public function assignPlan(Tenant $tenant, Plan $plan, array $data = []): TenantSubscription
    {
        $existing = $this->getCurrentSubscription($tenant);

        $oldPlanId = $existing?->plan_id;
        $oldStatus = $existing?->status;

        $status = $data['status'] ?? 'active';
        $startsAt = $data['starts_at'] ?? now();
        $periodEndsAt = $data['current_period_ends_at'] ?? null;

        if ($plan->billing_cycle === 'trial') {
            $trialDays = $plan->trial_days ?? 14;
            $status = 'trialing';
            $periodEndsAt = $startsAt->copy()->addDays($trialDays);
        }

        if ($existing) {
            $existing->update([
                'plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => $startsAt,
                'trial_ends_at' => $plan->billing_cycle === 'trial' ? $periodEndsAt : ($data['trial_ends_at'] ?? null),
                'current_period_starts_at' => $startsAt,
                'current_period_ends_at' => $periodEndsAt,
                'cancelled_at' => null,
                'metadata' => $data['metadata'] ?? $existing->metadata,
            ]);

            $this->writeHistory(
                $tenant->id,
                $existing->id,
                action: 'plan_changed',
                oldPlanId: $oldPlanId,
                newPlanId: $plan->id,
                oldStatus: $oldStatus,
                newStatus: $status,
                note: $data['note'] ?? null,
            );

            return $existing->fresh();
        }

        $subscription = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'starts_at' => $startsAt,
            'trial_ends_at' => $plan->billing_cycle === 'trial' ? $periodEndsAt : ($data['trial_ends_at'] ?? null),
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $periodEndsAt,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $this->writeHistory(
            $tenant->id,
            $subscription->id,
            action: 'plan_assigned',
            newPlanId: $plan->id,
            newStatus: $status,
            note: $data['note'] ?? null,
        );

        return $subscription;
    }

    public function updateSubscription(Tenant $tenant, array $data): ?TenantSubscription
    {
        $subscription = $this->getCurrentSubscription($tenant);

        if (! $subscription) {
            if (isset($data['plan_id'])) {
                $plan = Plan::find($data['plan_id']);
                if ($plan) {
                    return $this->assignPlan($tenant, $plan, $data);
                }
            }

            return null;
        }

        $oldPlanId = $subscription->plan_id;
        $oldStatus = $subscription->status;

        $updateData = [];

        if (isset($data['plan_id'])) {
            $updateData['plan_id'] = $data['plan_id'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (array_key_exists('trial_ends_at', $data)) {
            $updateData['trial_ends_at'] = $data['trial_ends_at'];
        }

        if (array_key_exists('current_period_ends_at', $data)) {
            $updateData['current_period_ends_at'] = $data['current_period_ends_at'];
        }

        if (isset($data['status']) && $data['status'] === 'cancelled') {
            $updateData['cancelled_at'] = now();
        }

        if (isset($data['status']) && $data['status'] === 'active' && $subscription->status === 'cancelled') {
            $updateData['cancelled_at'] = null;
        }

        $subscription->update($updateData);

        $action = 'status_changed';
        if (isset($data['plan_id']) && $data['plan_id'] !== $oldPlanId) {
            $action = 'plan_changed';
        }
        if (isset($data['status']) && $data['status'] === 'cancelled') {
            $action = 'cancelled';
        }
        if (isset($data['status']) && $data['status'] === 'active' && $oldStatus === 'cancelled') {
            $action = 'reactivated';
        }
        if (isset($data['status']) && $data['status'] === 'expired') {
            $action = 'expired';
        }
        if (array_key_exists('trial_ends_at', $data) && $data['trial_ends_at'] !== null) {
            $action = 'trial_extended';
        }

        $this->writeHistory(
            $tenant->id,
            $subscription->id,
            action: $action,
            oldPlanId: $oldPlanId,
            newPlanId: $subscription->plan_id,
            oldStatus: $oldStatus,
            newStatus: $subscription->status,
            note: $data['note'] ?? null,
        );

        return $subscription->fresh();
    }

    public function expireIfNeeded(Tenant $tenant): ?TenantSubscription
    {
        $subscription = $this->getCurrentSubscription($tenant);

        if (! $subscription) {
            return null;
        }

        if ($subscription->status === 'trialing' && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast()) {
            $oldStatus = $subscription->status;
            $subscription->update(['status' => 'expired']);

            $this->writeHistory(
                $tenant->id,
                $subscription->id,
                action: 'expired',
                oldStatus: $oldStatus,
                newStatus: 'expired',
                note: 'Trial period expired automatically.',
            );

            return $subscription->fresh();
        }

        if ($subscription->status === 'active' && $subscription->current_period_ends_at && $subscription->current_period_ends_at->isPast()) {
            $oldStatus = $subscription->status;
            $subscription->update(['status' => 'expired']);

            $this->writeHistory(
                $tenant->id,
                $subscription->id,
                action: 'expired',
                oldStatus: $oldStatus,
                newStatus: 'expired',
                note: 'Subscription period expired automatically.',
            );

            return $subscription->fresh();
        }

        return $subscription;
    }

    public function isUsable(Tenant $tenant): bool
    {
        $subscription = $this->expireIfNeeded($tenant);

        if (! $subscription) {
            return false;
        }

        return $subscription->isUsable();
    }

    public function canCreateUser(Tenant $tenant, string $role): bool
    {
        $subscription = $this->getCurrentSubscription($tenant);

        if (! $subscription || ! $subscription->isUsable()) {
            return false;
        }

        $plan = $subscription->plan;

        if (! $plan) {
            return true;
        }

        $limitField = match ($role) {
            'owner' => 'max_owners',
            'staff', 'manager', 'cashier' => 'max_staff',
            'kitchen' => 'max_kitchen',
            default => null,
        };

        if ($limitField === null) {
            return true;
        }

        $limit = $plan->$limitField;

        if ($limit === null) {
            return true;
        }

        $tenant->run(function () use ($role, $limitField, &$count) {
            if ($limitField === 'max_staff') {
                $count = \DB::table('users')
                    ->whereIn('role', ['staff', 'manager', 'cashier'])
                    ->where('status', 'active')
                    ->count();
            } else {
                $roleMap = ['max_owners' => 'owner', 'max_kitchen' => 'kitchen'];
                $count = \DB::table('users')
                    ->where('role', $roleMap[$limitField] ?? $role)
                    ->where('status', 'active')
                    ->count();
            }
        });

        return $count < $limit;
    }

    public function getRoleLimit(Tenant $tenant, string $role): ?int
    {
        $subscription = $this->getCurrentSubscription($tenant);

        if (! $subscription) {
            return null;
        }

        $plan = $subscription->plan;

        if (! $plan) {
            return null;
        }

        $limitField = match ($role) {
            'owner' => 'max_owners',
            'staff', 'manager', 'cashier' => 'max_staff',
            'kitchen' => 'max_kitchen',
            default => null,
        };

        if ($limitField === null) {
            return null;
        }

        return $plan->$limitField;
    }

    public function getDaysLeft(TenantSubscription $subscription): ?int
    {
        return $subscription->getDaysLeft();
    }

    public function writeHistory(
        string $tenantId,
        ?string $subscriptionId = null,
        ?string $oldPlanId = null,
        ?string $newPlanId = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        string $action = 'updated',
        ?string $note = null,
        ?string $changedBy = null,
    ): SubscriptionHistory {
        return SubscriptionHistory::create([
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionId,
            'old_plan_id' => $oldPlanId,
            'new_plan_id' => $newPlanId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'action' => $action,
            'note' => $note,
            'changed_by' => $changedBy,
        ]);
    }
}
