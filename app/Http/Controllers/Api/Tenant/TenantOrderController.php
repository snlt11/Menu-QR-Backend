<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function markPaid(string $tenant_slug, string $orderId, LoyaltyService $loyalty): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
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

        DB::transaction(function () use ($order, $loyalty) {
            $existingPayment = DB::table('payments')
                ->where('order_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPayment) {
                DB::table('payments')->where('id', $existingPayment->id)->update([
                    'status' => 'paid',
                    'updated_at' => now(),
                ]);

                DB::table('payment_sessions')
                    ->where('payment_id', $existingPayment->id)
                    ->update(['status' => 'paid', 'updated_at' => now()]);

                DB::table('payment_status_histories')->insert([
                    'id' => (string) Str::uuid(),
                    'payment_id' => $existingPayment->id,
                    'old_status' => 'pending',
                    'new_status' => 'paid',
                    'provider_status' => 'MOCK_QR',
                    'message' => 'Mock QR payment confirmed',
                    'created_at' => now(),
                ]);
            } else {
                $paymentId = (string) Str::uuid();
                DB::table('payments')->insert([
                    'id' => $paymentId,
                    'order_id' => $order->id,
                    'method' => 'qr_payment',
                    'provider' => 'demo_qr',
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
                    'provider_status' => 'MOCK_QR',
                    'message' => 'Mock QR payment confirmed',
                    'created_at' => now(),
                ]);
            }

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
}
