<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\DemoQrPaymentProvider;
use App\Services\LoyaltyService;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashierController extends Controller
{
    public function unpaid(): JsonResponse
    {
        $orders = DB::table('orders')
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'status' => 200,
            'data' => $orders->map(fn ($o) => OrderFormatHelper::format($o))->values(),
        ]);
    }

    public function generateBill(Request $request, string $tenant_slug, string $orderId, DemoQrPaymentProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'in:qr_payment,cash'],
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Order already paid.'], 409);
        }

        $paymentId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $session = $provider->createSession($order->order_number, (float) $order->payable_amount);

        DB::transaction(function () use ($paymentId, $sessionId, $order, $data, $session) {
            DB::table('payments')->insert([
                'id' => $paymentId,
                'order_id' => $order->id,
                'method' => $data['method'],
                'provider' => 'demo_qr',
                'status' => 'pending',
                'amount' => $order->payable_amount,
                'initiated_by' => 'cashier',
                'shown_on' => 'cashier_screen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_sessions')->insert([
                'id' => $sessionId,
                'payment_id' => $paymentId,
                'provider_reference' => $session['provider_reference'],
                'qr_payload' => $session['qr_payload'],
                'status' => 'pending',
                'expires_at' => $session['expires_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->where('id', $order->id)->update([
                'status' => 'checkout_requested',
                'payment_status' => 'pending',
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 201,
            'data' => [
                'payment' => DB::table('payments')->where('id', $paymentId)->first(),
                'session' => DB::table('payment_sessions')->where('id', $sessionId)->first(),
            ],
        ], 201);
    }

    public function confirmCash(string $tenant_slug, string $orderId, LoyaltyService $loyalty): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Order already paid.'], 409);
        }

        $paymentId = (string) Str::uuid();

        DB::transaction(function () use ($paymentId, $order, $loyalty) {
            DB::table('payments')->insert([
                'id' => $paymentId,
                'order_id' => $order->id,
                'method' => 'cash',
                'provider' => 'cash',
                'status' => 'paid',
                'amount' => $order->payable_amount,
                'initiated_by' => 'cashier',
                'shown_on' => 'cashier_screen',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('payment_status_histories')->insert([
                'id' => (string) Str::uuid(),
                'payment_id' => $paymentId,
                'old_status' => null,
                'new_status' => 'paid',
                'provider_status' => 'CASH',
                'message' => 'Cash payment accepted',
                'created_at' => now(),
            ]);

            $earned = 0;
            if ($order->customer_type === 'member' && $order->customer_id) {
                if ((int) $order->redeemed_points > 0) {
                    $loyalty->deductRedeemedPoints($order->customer_id, $order->id, (int) $order->redeemed_points, $order->order_number);
                }
                $earned = $loyalty->pointsEarnedFor((float) $order->payable_amount);
                if ($earned > 0) {
                    $loyalty->awardEarnedPoints($order->customer_id, $order->id, $earned, $order->order_number);
                }
            }

            $newStatus = $order->status;
            if (in_array($order->status, ['pending_payment', 'checkout_requested', 'submitted'])) {
                $newStatus = 'submitted';
            }

            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
                'earned_points' => $earned,
                'updated_at' => now(),
            ]);
        });

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
