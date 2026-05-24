<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\GenerateBillRequest;
use App\Models\Order;
use App\Services\OrderFormatHelper;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class CashierController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function unpaid(): JsonResponse
    {
        $orders = Order::whereIn('payment_status', ['unpaid', 'pending'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $orders->map(fn ($o) => OrderFormatHelper::format($o))->values(),
        ]);
    }

    public function generateBill(GenerateBillRequest $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Order already paid.'], 409);
        }

        $result = $this->paymentService->createQrSession(
            $order,
            $request->validated()['method'],
            'cashier',
            'cashier_screen',
        );

        $order->update([
            'status' => 'checkout_requested',
            'payment_status' => 'pending',
        ]);

        return response()->json([
            'status' => 201,
            'data' => $result,
        ], 201);
    }

    public function confirmCash(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Order already paid.'], 409);
        }

        $this->paymentService->confirmCashPayment($order);

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::formatWithItems(
                Order::where('id', $orderId)->first()
            ),
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
