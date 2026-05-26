<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderBroadcastService;
use App\Services\OrderFormatHelper;
use App\Services\OrderStatusService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantOrderController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $statusService,
        private readonly PaymentService $paymentService,
        private readonly OrderBroadcastService $broadcaster,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $search = $request->query('search', '');
        $tableId = $request->query('table_id', '');

        $query = Order::with('table', 'customer')
            ->when($tableId, fn ($q) => $q->where('table_id', $tableId))
            ->when($search, function ($q) use ($search) {
                $like = '%'.strtolower($search).'%';
                $q->where(function ($q2) use ($like) {
                    $q2->whereRaw('LOWER(order_number) LIKE ?', [$like]);
                });
            });

        $allOrders = $query->orderByDesc('updated_at')->get();

        $counts = $this->statusService->computeCounts($allOrders);
        $filtered = $this->statusService->filterForTab($allOrders, $tab);

        ['items' => $items, 'meta' => $meta] = $this->statusService->paginate($filtered, $page, $perPage);

        return response()->json([
            'status' => 200,
            'data' => $items->map(fn ($o) => OrderFormatHelper::format($o))->values(),
            'meta' => $meta,
            'counts' => $counts,
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

    public function markPaid(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        $approvalStatus = $order->approval_status ?? 'not_required';

        if (! in_array($order->payment_status, ['unpaid', 'pending'])) {
            return response()->json(['status' => 409, 'message' => 'Order is not in a payable state.'], 409);
        }

        if ($approvalStatus === 'approval_pending') {
            return response()->json(['status' => 422, 'message' => 'Guest order must be approved before payment.'], 422);
        }

        if ($approvalStatus === 'rejected') {
            return response()->json(['status' => 422, 'message' => 'Rejected orders cannot be paid.'], 422);
        }

        if (in_array($order->status, ['completed', 'cancelled', 'expired'])) {
            return response()->json(['status' => 422, 'message' => 'Cannot pay a completed, cancelled, or expired order.'], 422);
        }

        $canPay = in_array($order->payment_status, ['unpaid', 'pending'])
            && ($approvalStatus === 'not_required' || $approvalStatus === 'approved');

        if (! $canPay) {
            return response()->json(['status' => 422, 'message' => 'This order cannot be paid right now.'], 422);
        }

        $this->paymentService->markOrderPaid($order);

        $this->broadcaster->broadcastUpdate(Order::where('id', $orderId)->first(), 'payment_updated');

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems(
                Order::where('id', $orderId)->first()
            ),
        ]);
    }
}
