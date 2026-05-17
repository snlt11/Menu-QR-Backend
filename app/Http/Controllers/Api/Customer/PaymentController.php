<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\DemoQrPaymentProvider;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function createSession(Request $request, string $tenant_slug, string $orderId, DemoQrPaymentProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'in:qr_payment,cash'],
            'shown_on' => ['sometimes', 'nullable', 'string', 'in:customer_phone,cashier_screen'],
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
                'initiated_by' => null,
                'shown_on' => $data['shown_on'] ?? 'customer_phone',
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

            DB::table('payment_status_histories')->insert([
                'id' => (string) Str::uuid(),
                'payment_id' => $paymentId,
                'old_status' => null,
                'new_status' => 'pending',
                'provider_status' => 'PENDING',
                'message' => 'Payment session created',
                'created_at' => now(),
            ]);

            DB::table('orders')->where('id', $order->id)->update([
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

    public function confirmDemo(string $tenant_slug, string $sessionId, LoyaltyService $loyalty): JsonResponse
    {
        $session = DB::table('payment_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            return response()->json(['status' => 404, 'message' => 'Session not found.'], 404);
        }
        if ($session->status === 'paid') {
            return response()->json(['status' => 409, 'message' => 'Session already paid.'], 409);
        }

        $payment = DB::table('payments')->where('id', $session->payment_id)->first();
        $order = DB::table('orders')->where('id', $payment->order_id)->first();

        DB::transaction(function () use ($session, $payment, $order, $loyalty) {
            DB::table('payment_sessions')->where('id', $session->id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
            DB::table('payments')->where('id', $payment->id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
            DB::table('payment_status_histories')->insert([
                'id' => (string) Str::uuid(),
                'payment_id' => $payment->id,
                'old_status' => 'pending',
                'new_status' => 'paid',
                'provider_status' => 'SUCCESS',
                'message' => 'Payment confirmed successfully',
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

            $nextStatus = $order->payment_timing === 'pay_before_prepare' ? 'accepted' : 'completed';
            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => 'paid',
                'status' => $nextStatus,
                'earned_points' => $earned,
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 200,
            'data' => DB::table('orders')->where('id', $order->id)->first(),
        ]);
    }
}
