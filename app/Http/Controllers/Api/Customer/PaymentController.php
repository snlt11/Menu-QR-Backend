<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\CreatePaymentSessionRequest;
use App\Models\Order;
use App\Models\PaymentSession;
use App\Services\OrderBroadcastService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderBroadcastService $broadcaster,
    ) {}

    public function createSession(CreatePaymentSessionRequest $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        $accessToken = $request->header('X-Order-Access-Token');
        if ($accessToken && $order->public_access_token !== $accessToken) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Order already paid.'], 409);
        }

        $data = $request->validated();

        $result = $this->paymentService->createQrSession(
            $order,
            $data['method'],
            null,
            $data['shown_on'] ?? 'customer_phone',
        );

        return response()->json([
            'status' => 201,
            'data' => $result,
        ], 201);
    }

    public function confirmDemo(string $tenant_slug, string $sessionId): JsonResponse
    {
        $session = PaymentSession::where('id', $sessionId)->first();
        if (! $session) {
            return response()->json(['status' => 404, 'message' => 'Session not found.'], 404);
        }
        if ($session->status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Session already paid.'], 409);
        }

        $order = $this->paymentService->confirmDemoPayment($sessionId);

        $this->broadcaster->broadcastUpdate($order, 'payment_updated');

        return response()->json([
            'status' => 200,
            'data' => Order::where('id', $order->id)->first(),
        ]);
    }
}
