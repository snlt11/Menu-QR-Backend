<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $search = $request->query('search', '');
        $tableId = $request->query('table_id', '');

        $query = DB::table('orders')
            ->when($tableId, fn ($q) => $q->where('table_id', $tableId))
            ->when($search, function ($q) use ($search) {
                $like = '%'.strtolower($search).'%';
                $q->where(function ($q2) use ($like) {
                    $q2->whereRaw('LOWER(order_code) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(order_number) LIKE ?', [$like]);
                });
            });

        $allOrders = $query->orderByDesc('updated_at')->get();

        $filtered = $allOrders->filter(function ($o) use ($tab) {
            $s = $o->status;
            $ps = $o->payment_status;
            $as = $o->approval_status ?? 'not_required';
            $pt = $o->payment_timing ?? 'pay_after_meal';
            $isUnpaid = in_array($ps, ['unpaid', 'pending']);
            $isPaid = $ps === 'paid';
            $isWaitingApproval = $as === 'approval_pending';
            $isRejected = $as === 'rejected';
            $isCompleted = in_array($s, ['completed', 'served']);
            $isFinal = in_array($s, ['completed', 'cancelled', 'expired']);

            $kitchenStatuses = ['submitted', 'accepted', 'preparing'];
            $isKitchenActive = ! $isFinal
                && $as !== 'approval_pending'
                && (
                    ($pt === 'pay_after_meal' && in_array($s, $kitchenStatuses))
                    || ($pt === 'pay_before_prepare' && $isPaid && in_array($s, $kitchenStatuses))
                );

            $needsAttention = false;
            if (! $isFinal && ! $isRejected) {
                if ($isWaitingApproval) {
                    $needsAttention = true;
                }
                if (in_array($ps, ['failed', 'expired'])) {
                    $needsAttention = true;
                }
                if ($isUnpaid) {
                    $needsAttention = true;
                }
                if ($isPaid && in_array($s, ['submitted', 'accepted'])) {
                    $needsAttention = true;
                }
                if ($s === 'ready') {
                    $needsAttention = true;
                }
                if ($ps === 'pending') {
                    $needsAttention = true;
                }
                if ($isKitchenActive) {
                    $needsAttention = true;
                }
            }

            return match ($tab) {
                'attention' => $needsAttention,
                'approval' => $isWaitingApproval,
                'unpaid' => $isUnpaid && ! $isRejected,
                'rejected' => $isRejected,
                'paid' => $isPaid,
                'kitchen' => $isKitchenActive,
                'completed' => $isCompleted,
                default => true,
            };
        });

        $filtered = $filtered->values();
        $total = $filtered->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $safePage = min($page, $lastPage);
        $items = $filtered->slice(($safePage - 1) * $perPage, $perPage);

        $counts = (object) [
            'all' => $allOrders->count(),
            'attention' => 0,
            'approval' => 0,
            'unpaid' => 0,
            'rejected' => 0,
            'paid' => 0,
            'kitchen' => 0,
            'completed' => 0,
            'sales' => 0,
            'tables' => [],
        ];

        $tableMap = [];
        foreach ($allOrders as $o) {
            $s = $o->status;
            $ps = $o->payment_status;
            $as = $o->approval_status ?? 'not_required';
            $pt = $o->payment_timing ?? 'pay_after_meal';
            $oUnpaid = in_array($ps, ['unpaid', 'pending']);
            $oPaid = $ps === 'paid';
            $oWaitingApproval = $as === 'approval_pending';
            $oRejected = $as === 'rejected';
            $oCompleted = in_array($s, ['completed', 'served']);
            $oFinal = in_array($s, ['completed', 'cancelled', 'expired']);

            $kitchenStatuses = ['submitted', 'accepted', 'preparing'];
            $oKitchenActive = ! $oFinal
                && $as !== 'approval_pending'
                && (
                    ($pt === 'pay_after_meal' && in_array($s, $kitchenStatuses))
                    || ($pt === 'pay_before_prepare' && $oPaid && in_array($s, $kitchenStatuses))
                );

            $oNeedsAttention = false;
            if (! $oFinal && ! $oRejected) {
                if ($oWaitingApproval) {
                    $oNeedsAttention = true;
                }
                if (in_array($ps, ['failed', 'expired'])) {
                    $oNeedsAttention = true;
                }
                if ($oUnpaid) {
                    $oNeedsAttention = true;
                }
                if ($oPaid && in_array($s, ['submitted', 'accepted'])) {
                    $oNeedsAttention = true;
                }
                if ($s === 'ready') {
                    $oNeedsAttention = true;
                }
                if ($ps === 'pending') {
                    $oNeedsAttention = true;
                }
                if ($oKitchenActive) {
                    $oNeedsAttention = true;
                }
            }

            if ($oNeedsAttention) {
                $counts->attention++;
            }
            if ($oWaitingApproval) {
                $counts->approval++;
            }
            if ($oUnpaid && ! $oRejected) {
                $counts->unpaid++;
            }
            if ($oRejected) {
                $counts->rejected++;
            }
            if ($oPaid) {
                $counts->paid++;
                $counts->sales += (float) $o->payable_amount;
            }
            if ($oKitchenActive) {
                $counts->kitchen++;
            }
            if ($oCompleted) {
                $counts->completed++;
            }

            $tLabel = trim(($o->table_name ?? '') ?: ($o->table_number ?? '') ?: 'No table');
            $tId = $o->table_id ?? '';
            if ($tId && ! isset($tableMap[$tId])) {
                $tableMap[$tId] = ['id' => $tId, 'name' => $tLabel, 'count' => 0];
            }
            if ($tId) {
                $tableMap[$tId]['count']++;
            }
        }

        $counts->tables = array_values($tableMap);
        usort($counts->tables, fn ($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'status' => 200,
            'data' => $items->map(fn ($o) => OrderFormatHelper::format($o))->values(),
            'meta' => [
                'current_page' => $safePage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? ($safePage - 1) * $perPage + 1 : 0,
                'to' => min($safePage * $perPage, $total),
            ],
            'counts' => $counts,
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

            $earned = $loyalty->processOrderPayment($order);

            $newStatus = $order->status;
            if (in_array($order->status, ['pending_payment', 'checkout_requested', 'submitted'])) {
                $newStatus = 'submitted';
            }

            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => 'paid',
                'status' => $newStatus,
                'earned_points' => $earned,
                'paid_at' => now(),
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
