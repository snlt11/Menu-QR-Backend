<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\RejectKitchenOrderRequest;
use App\Http\Requests\Api\Tenant\UpdateKitchenOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderBroadcastService;
use App\Services\OrderFormatHelper;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $statusService,
        private readonly OrderBroadcastService $broadcaster,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'active');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 8)));

        $allOrders = Order::with('table', 'customer')
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
            ->orderByDesc('updated_at')
            ->get();

        $counts = $this->statusService->computeKitchenCounts($allOrders);
        $filtered = $this->statusService->filterKitchenForTab($allOrders, $tab);

        ['items' => $items, 'meta' => $meta] = $this->statusService->paginate($filtered, $page, $perPage);

        return response()->json([
            'status' => 200,
            'data' => $items->map(fn ($o) => OrderFormatHelper::format($o))->values(),
            'meta' => $meta,
            'counts' => $counts,
        ]);
    }

    public function updateStatus(UpdateKitchenOrderStatusRequest $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_type === 'guest' && ($order->approval_status ?? 'not_required') === 'approval_pending') {
            return response()->json([
                'status' => 422,
                'message' => 'Guest order must be approved before kitchen preparation.',
            ], 422);
        }

        $order->update(['status' => $request->validated()['status']]);

        $this->broadcaster->broadcastUpdate($order->fresh(), 'status_updated');

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems($order->fresh()),
        ]);
    }

    public function approve(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_type !== 'guest') {
            return response()->json(['status' => 422, 'message' => 'Only guest orders require approval.'], 422);
        }

        if (($order->approval_status ?? 'not_required') !== 'approval_pending') {
            return response()->json(['status' => 422, 'message' => 'Order is not pending approval.'], 422);
        }

        $order->update([
            'approval_status' => 'approved',
            'status' => 'submitted',
        ]);

        $this->broadcaster->broadcastUpdate($order->fresh(), 'approved');

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems($order->fresh()),
        ]);
    }

    public function reject(RejectKitchenOrderRequest $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_type !== 'guest') {
            return response()->json(['status' => 422, 'message' => 'Only guest orders require approval.'], 422);
        }

        if (($order->approval_status ?? 'not_required') !== 'approval_pending') {
            return response()->json(['status' => 422, 'message' => 'Order is not pending approval.'], 422);
        }

        $order->update([
            'approval_status' => 'rejected',
            'status' => 'cancelled',
        ]);

        $this->broadcaster->broadcastUpdate($order->fresh(), 'rejected');

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems($order->fresh()),
        ]);
    }

    public function show(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems($order),
        ]);
    }
}
