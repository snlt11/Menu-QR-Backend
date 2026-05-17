<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    public function index(): JsonResponse
    {
        // Kitchen visibility per design §25:
        // pay_before_prepare: only paid orders.
        // pay_after_meal: orders as soon as submitted (regardless of payment).
        $orders = DB::table('orders')
            ->where(function ($q) {
                $q->where('payment_timing', 'pay_after_meal')
                  ->whereIn('status', ['submitted', 'accepted', 'preparing', 'ready']);
            })
            ->orWhere(function ($q) {
                $q->where('payment_timing', 'pay_before_prepare')
                  ->where('payment_status', 'paid')
                  ->whereIn('status', ['accepted', 'preparing', 'ready']);
            })
            ->orderBy('created_at')
            ->get();

        $items = DB::table('order_items')
            ->whereIn('order_id', $orders->pluck('id'))
            ->get()
            ->groupBy('order_id');

        return response()->json([
            'status' => 200,
            'data' => $orders->map(fn ($o) => [
                'order' => $o,
                'items' => $items->get($o->id, collect())->values(),
            ])->values(),
        ]);
    }

    public function updateStatus(Request $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:accepted,preparing,ready,served'],
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('orders')->where('id', $orderId)->first(),
        ]);
    }
}
