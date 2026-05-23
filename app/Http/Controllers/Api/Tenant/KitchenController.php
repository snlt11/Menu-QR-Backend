<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'active');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 8)));

        $baseQuery = DB::table('orders')
            ->where(function ($q) {
                $q->where('payment_timing', 'pay_after_meal')
                    ->whereIn('status', ['submitted', 'accepted', 'preparing', 'ready', 'completed']);
            })
            ->orWhere(function ($q) {
                $q->where('payment_timing', 'pay_before_prepare')
                    ->where('payment_status', 'paid')
                    ->whereIn('status', ['accepted', 'preparing', 'ready', 'completed']);
            })
            ->orWhere('approval_status', 'approval_pending');

        $countsQuery = clone $baseQuery;
        $allOrders = $countsQuery->orderBy('updated_at', 'desc')->get();

        $counts = (object) [
            'active' => 0,
            'approval' => 0,
            'preparing' => 0,
            'ready' => 0,
            'completed' => 0,
            'new' => 0,
        ];

        foreach ($allOrders as $o) {
            $s = $o->status;
            $a = $o->approval_status ?? 'not_required';
            $isApproval = $a === 'approval_pending';
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

            if (! $isFinal && ! $isApproval && (in_array($s, ['submitted', 'accepted', 'preparing']))) {
                $counts->active++;
            }
        }

        $filtered = match ($tab) {
            'active' => $allOrders->filter(fn ($o) => ($o->approval_status ?? 'not_required') !== 'approval_pending'
                && ! in_array($o->status, ['completed', 'cancelled', 'expired'])
                && in_array($o->status, ['submitted', 'accepted', 'preparing'])),
            'approval' => $allOrders->filter(fn ($o) => ($o->approval_status ?? 'not_required') === 'approval_pending'),
            'preparing' => $allOrders->filter(fn ($o) => ($o->approval_status ?? 'not_required') !== 'approval_pending'
                && $o->status === 'preparing'),
            'ready' => $allOrders->filter(fn ($o) => ($o->approval_status ?? 'not_required') !== 'approval_pending'
                && $o->status === 'ready'),
            'completed' => $allOrders->filter(fn ($o) => in_array($o->status, ['completed', 'served'])),
            default => $allOrders,
        };

        $filtered = $filtered->values();
        $total = $filtered->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $safePage = min($page, $lastPage);
        $items = $filtered->slice(($safePage - 1) * $perPage, $perPage);

        return response()->json([
            'status' => 200,
            'data' => $items->map(fn ($o) => OrderFormatHelper::format($o))->values(),
            'meta' => [
                'current_page' => $safePage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? ($safePage - 1) * $perPage + 1 : 0,
                'to' => min($safePage * $perPage, $total),
            ],
            'counts' => $counts,
        ]);
    }

    public function updateStatus(Request $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:accepted,preparing,ready,served,completed'],
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_type === 'guest' && ($order->approval_status ?? 'not_required') === 'approval_pending') {
            return response()->json([
                'status' => 422,
                'message' => 'Guest order must be approved before kitchen preparation.',
            ], 422);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems(
                DB::table('orders')->where('id', $orderId)->first()
            ),
        ]);
    }

    public function approve(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_type !== 'guest') {
            return response()->json(['status' => 422, 'message' => 'Only guest orders require approval.'], 422);
        }

        if (($order->approval_status ?? 'not_required') !== 'approval_pending') {
            return response()->json(['status' => 422, 'message' => 'Order is not pending approval.'], 422);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'approval_status' => 'approved',
            'status' => 'submitted',
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems(
                DB::table('orders')->where('id', $orderId)->first()
            ),
        ]);
    }

    public function reject(Request $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_type !== 'guest') {
            return response()->json(['status' => 422, 'message' => 'Only guest orders require approval.'], 422);
        }

        if (($order->approval_status ?? 'not_required') !== 'approval_pending') {
            return response()->json(['status' => 422, 'message' => 'Order is not pending approval.'], 422);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'approval_status' => 'rejected',
            'status' => 'cancelled',
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems(
                DB::table('orders')->where('id', $orderId)->first()
            ),
        ]);
    }

    public function show(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems($order),
        ]);
    }
}
