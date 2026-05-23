<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TenantOrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = DB::table('orders')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($o) => OrderFormatHelper::format($o))
            ->values();

        return response()->json([
            'status' => 200,
            'data' => $orders,
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
