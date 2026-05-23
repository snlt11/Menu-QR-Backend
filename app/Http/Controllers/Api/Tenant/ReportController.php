<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $today = today();

        $todayOrders = DB::table('orders')->whereDate('created_at', $today);
        $todayPaid = (clone $todayOrders)->where('payment_status', 'paid');

        $unpaidBills = DB::table('orders')->whereIn('payment_status', ['unpaid', 'pending'])->count();

        $waitingApproval = DB::table('orders')
            ->where('approval_status', 'approval_pending')
            ->count();

        $kitchenActive = DB::table('orders')
            ->whereIn('status', ['accepted', 'preparing', 'ready'])
            ->count();

        $guestOrders = (clone $todayOrders)->where('customer_type', 'guest')->count();
        $loggedInOrders = (clone $todayOrders)->where('customer_type', 'member')->count();

        $popularItems = DB::table('order_items')
            ->select('snapshot_name', DB::raw('SUM(quantity) as units'))
            ->groupBy('snapshot_name')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        $pointsRedeemedToday = DB::table('loyalty_point_transactions')
            ->where('type', 'redeem')
            ->whereDate('created_at', $today)
            ->sum(DB::raw('ABS(points)'));

        $memberCount = DB::table('customer_profiles')->count();

        $needsAttention = DB::table('orders')
            ->where(function ($q) {
                $q->where('approval_status', 'approval_pending')
                    ->orWhereIn('payment_status', ['failed', 'expired'])
                    ->orWhere(function ($sq) {
                        $sq->where('payment_status', 'unpaid')
                            ->where('created_at', '<', now()->subMinutes(30));
                    });
            })
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($o) => OrderFormatHelper::format($o))
            ->values();

        return response()->json([
            'status' => 200,
            'data' => [
                'today' => [
                    'sales_amount' => (float) (clone $todayPaid)->sum('payable_amount'),
                    'orders_count' => (clone $todayOrders)->count(),
                    'paid_orders_count' => (clone $todayPaid)->count(),
                    'guest_orders' => $guestOrders,
                    'logged_in_orders' => $loggedInOrders,
                ],
                'unpaid_bills' => $unpaidBills,
                'waiting_approval' => $waitingApproval,
                'kitchen_active' => $kitchenActive,
                'needs_attention' => $needsAttention,
                'popular_items' => $popularItems,
                'points_redeemed_today' => (int) $pointsRedeemedToday,
                'member_count' => $memberCount,
            ],
        ]);
    }
}
