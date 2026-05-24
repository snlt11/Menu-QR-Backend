<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSession;
use App\Models\PaymentStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly DemoQrPaymentProvider $provider,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function createQrSession(Order $order, string $method, ?string $initiatedBy = null, string $shownOn = 'customer_phone'): array
    {
        $paymentId = (string) Str::uuid();
        $sessionId = (string) Str::uuid();
        $session = $this->provider->createSession($order->order_number, (float) $order->payable_amount);

        DB::transaction(function () use ($paymentId, $sessionId, $order, $method, $initiatedBy, $shownOn, $session) {
            Payment::create([
                'id' => $paymentId,
                'order_id' => $order->id,
                'method' => $method,
                'provider' => 'demo_qr',
                'status' => 'pending',
                'amount' => $order->payable_amount,
                'initiated_by' => $initiatedBy,
                'shown_on' => $shownOn,
            ]);

            PaymentSession::create([
                'id' => $sessionId,
                'payment_id' => $paymentId,
                'provider_reference' => $session['provider_reference'],
                'qr_payload' => $session['qr_payload'],
                'status' => 'pending',
                'expires_at' => $session['expires_at'],
            ]);

            PaymentStatusHistory::create([
                'id' => (string) Str::uuid(),
                'payment_id' => $paymentId,
                'old_status' => null,
                'new_status' => 'pending',
                'provider_status' => 'PENDING',
                'message' => 'Payment session created',
            ]);

            $order->update(['payment_status' => 'pending']);
        });

        return [
            'payment' => Payment::where('id', $paymentId)->first(),
            'session' => PaymentSession::where('id', $sessionId)->first(),
        ];
    }

    public function confirmCashPayment(Order $order): Order
    {
        $paymentId = (string) Str::uuid();

        DB::transaction(function () use ($paymentId, $order) {
            Payment::create([
                'id' => $paymentId,
                'order_id' => $order->id,
                'method' => 'cash',
                'provider' => 'cash',
                'status' => 'paid',
                'amount' => $order->payable_amount,
                'initiated_by' => 'cashier',
                'shown_on' => 'cashier_screen',
            ]);

            PaymentStatusHistory::create([
                'id' => (string) Str::uuid(),
                'payment_id' => $paymentId,
                'old_status' => null,
                'new_status' => 'paid',
                'provider_status' => 'CASH',
                'message' => 'Cash payment accepted',
            ]);

            $earned = $this->loyalty->processOrderPayment($order);

            $newStatus = $order->status;
            if (in_array($order->status, ['pending_payment', 'checkout_requested', 'submitted'])) {
                $newStatus = 'submitted';
            }

            $order->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
                'earned_points' => $earned,
                'paid_at' => now(),
            ]);
        });

        return $order->fresh();
    }

    public function confirmDemoPayment(string $sessionId): Order
    {
        $session = PaymentSession::where('id', $sessionId)->first();
        $payment = Payment::where('id', $session->payment_id)->first();
        $order = Order::findOrFail($payment->order_id);

        DB::transaction(function () use ($session, $payment, $order) {
            $session->update(['status' => 'paid']);
            $payment->update(['status' => 'paid']);

            PaymentStatusHistory::create([
                'id' => (string) Str::uuid(),
                'payment_id' => $payment->id,
                'old_status' => 'pending',
                'new_status' => 'paid',
                'provider_status' => 'SUCCESS',
                'message' => 'Payment confirmed successfully',
            ]);

            $earned = $this->loyalty->processOrderPayment($order);

            $nextStatus = $order->status;
            if (in_array($order->status, ['pending_payment', 'checkout_requested'])) {
                $nextStatus = 'submitted';
            }

            $order->update([
                'payment_status' => 'paid',
                'status' => $nextStatus,
                'earned_points' => $earned,
                'paid_at' => now(),
            ]);
        });

        return $order->fresh();
    }

    public function markOrderPaid(Order $order): Order
    {
        DB::transaction(function () use ($order) {
            $existingPayment = Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPayment) {
                $existingPayment->update(['status' => 'paid']);

                PaymentSession::where('payment_id', $existingPayment->id)
                    ->update(['status' => 'paid']);

                PaymentStatusHistory::create([
                    'id' => (string) Str::uuid(),
                    'payment_id' => $existingPayment->id,
                    'old_status' => 'pending',
                    'new_status' => 'paid',
                    'provider_status' => 'MOCK_QR',
                    'message' => 'Mock QR payment confirmed',
                ]);
            } else {
                $paymentId = (string) Str::uuid();
                Payment::create([
                    'id' => $paymentId,
                    'order_id' => $order->id,
                    'method' => 'qr_payment',
                    'provider' => 'demo_qr',
                    'status' => 'paid',
                    'amount' => $order->payable_amount,
                    'initiated_by' => 'cashier',
                    'shown_on' => 'cashier_screen',
                ]);

                PaymentStatusHistory::create([
                    'id' => (string) Str::uuid(),
                    'payment_id' => $paymentId,
                    'old_status' => null,
                    'new_status' => 'paid',
                    'provider_status' => 'MOCK_QR',
                    'message' => 'Mock QR payment confirmed',
                ]);
            }

            $earned = $this->loyalty->processOrderPayment($order);

            $newStatus = $order->status;
            if (in_array($order->status, ['pending_payment', 'checkout_requested', 'submitted'])) {
                $newStatus = 'submitted';
            }

            $order->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
                'earned_points' => $earned,
                'paid_at' => now(),
            ]);
        });

        return $order->fresh();
    }
}
