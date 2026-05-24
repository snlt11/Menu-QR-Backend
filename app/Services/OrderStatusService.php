<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class OrderStatusService
{
    public function classifyOrder(Order $order): OrderClassification
    {
        $s = $order->status;
        $ps = $order->payment_status;
        $as = $order->approval_status ?? 'not_required';
        $pt = $order->payment_timing ?? 'pay_after_meal';

        $isUnpaid = in_array($ps, ['unpaid', 'pending']);
        $isPaid = $ps === 'paid';
        $isWaitingApproval = $as === 'approval_pending';
        $isRejected = $as === 'rejected';
        $isCompleted = in_array($s, ['completed', 'served']);
        $isFinal = in_array($s, ['completed', 'cancelled', 'expired']);

        $kitchenStatuses = ['submitted', 'accepted', 'preparing'];
        $isKitchenActive = ! $isFinal
            && $as !== 'approval_pending'
            && (
                ($pt === 'pay_after_meal' && in_array($s, $kitchenStatuses))
                || ($pt === 'pay_before_prepare' && $isPaid && in_array($s, $kitchenStatuses))
            );

        $needsAttention = false;
        if (! $isFinal && ! $isRejected) {
            if ($isWaitingApproval
                || in_array($ps, ['failed', 'expired'])
                || $isUnpaid
                || ($isPaid && in_array($s, ['submitted', 'accepted']))
                || $s === 'ready'
                || $ps === 'pending'
                || $isKitchenActive) {
                $needsAttention = true;
            }
        }

        return new OrderClassification(
            isUnpaid: $isUnpaid,
            isPaid: $isPaid,
            isWaitingApproval: $isWaitingApproval,
            isRejected: $isRejected,
            isCompleted: $isCompleted,
            isFinal: $isFinal,
            isKitchenActive: $isKitchenActive,
            needsAttention: $needsAttention,
        );
    }

    public function filterForTab(Collection $orders, string $tab): Collection
    {
        return $orders->filter(function (Order $order) use ($tab) {
            $c = $this->classifyOrder($order);

            return match ($tab) {
                'attention' => $c->needsAttention,
                'approval' => $c->isWaitingApproval,
                'unpaid' => $c->isUnpaid && ! $c->isRejected,
                'rejected' => $c->isRejected,
                'paid' => $c->isPaid,
                'kitchen' => $c->isKitchenActive,
                'completed' => $c->isCompleted,
                default => true,
            };
        })->values();
    }

    public function filterKitchenForTab(Collection $orders, string $tab): Collection
    {
        return $orders->filter(function (Order $order) use ($tab) {
            $s = $order->status;
            $as = $order->approval_status ?? 'not_required';
            $isApproval = $as === 'approval_pending';
            $isFinal = in_array($s, ['completed', 'cancelled', 'expired']);
            $isCompleted = in_array($s, ['completed', 'served']);

            return match ($tab) {
                'active' => ! $isApproval && ! $isFinal && in_array($s, ['submitted', 'accepted', 'preparing']),
                'approval' => $isApproval,
                'preparing' => ! $isApproval && $s === 'preparing',
                'ready' => ! $isApproval && $s === 'ready',
                'completed' => $isCompleted,
                default => true,
            };
        })->values();
    }

    public function computeCounts(Collection $orders): object
    {
        $counts = (object) [
            'all' => $orders->count(),
            'attention' => 0,
            'approval' => 0,
            'unpaid' => 0,
            'rejected' => 0,
            'paid' => 0,
            'kitchen' => 0,
            'completed' => 0,
            'sales' => 0,
            'tables' => [],
        ];

        $tableMap = [];

        foreach ($orders as $order) {
            $c = $this->classifyOrder($order);

            if ($c->needsAttention) {
                $counts->attention++;
            }
            if ($c->isWaitingApproval) {
                $counts->approval++;
            }
            if ($c->isUnpaid && ! $c->isRejected) {
                $counts->unpaid++;
            }
            if ($c->isRejected) {
                $counts->rejected++;
            }
            if ($c->isPaid) {
                $counts->paid++;
                $counts->sales += (float) $order->payable_amount;
            }
            if ($c->isKitchenActive) {
                $counts->kitchen++;
            }
            if ($c->isCompleted) {
                $counts->completed++;
            }

            $tLabel = trim(($order->table?->table_name ?? '') ?: ($order->table?->table_number ? "Table {$order->table->table_number}" : '') ?: 'No table');
            $tId = $order->table_id ?? '';
            if ($tId && ! isset($tableMap[$tId])) {
                $tableMap[$tId] = ['id' => $tId, 'name' => $tLabel, 'count' => 0];
            }
            if ($tId) {
                $tableMap[$tId]['count']++;
            }
        }

        $counts->tables = array_values($tableMap);
        usort($counts->tables, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $counts;
    }

    public function computeKitchenCounts(Collection $orders): object
    {
        $counts = (object) [
            'active' => 0,
            'approval' => 0,
            'preparing' => 0,
            'ready' => 0,
            'completed' => 0,
            'new' => 0,
        ];

        foreach ($orders as $order) {
            $s = $order->status;
            $as = $order->approval_status ?? 'not_required';
            $isApproval = $as === 'approval_pending';
            $isFinal = in_array($s, ['completed', 'cancelled', 'expired']);
            $isCompleted = in_array($s, ['completed', 'served']);

            if ($isApproval) {
                $counts->approval++;

                continue;
            }

            if ($isCompleted) {
                $counts->completed++;
            }

            if ($s === 'preparing') {
                $counts->preparing++;
            }

            if ($s === 'ready') {
                $counts->ready++;
            }

            if (in_array($s, ['submitted', 'accepted']) && ! $isFinal) {
                $counts->new++;
            }

            if (! $isFinal && ! $isApproval && in_array($s, ['submitted', 'accepted', 'preparing'])) {
                $counts->active++;
            }
        }

        return $counts;
    }

    public function paginate(Collection $items, int $page, int $perPage): array
    {
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $safePage = min($page, $lastPage);
        $sliced = $items->slice(($safePage - 1) * $perPage, $perPage);

        return [
            'items' => $sliced,
            'meta' => [
                'current_page' => $safePage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? ($safePage - 1) * $perPage + 1 : 0,
                'to' => min($safePage * $perPage, $total),
            ],
        ];
    }
}
