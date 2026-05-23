<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = DB::table('orders')
            ->where(function ($q) {
                $q->where('payment_timing', 'pay_after_meal')
                    ->whereIn('status', ['submitted', 'accepted', 'preparing', 'ready', 'completed']);
            })
            ->orWhere(function ($q) {
                $q->where('payment_timing', 'pay_before_prepare')
                    ->where('payment_status', 'paid')
                    ->whereIn('status', ['accepted', 'preparing', 'ready', 'completed']);
            })
            ->orWhere('approval_status', 'approval_pending')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $orders->map(fn ($o) => OrderFormatHelper::format($o))->values(),
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
