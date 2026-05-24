<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $today = today();

        $todayOrders = Order::whereDate('created_at', $today);
        $todayPaid = (clone $todayOrders)->where('payment_status', 'paid');

        $unpaidBills = Order::query()->unpaid()->count();

        $waitingApproval = Order::where('approval_status', 'approval_pending')->count();

        $kitchenActive = Order::whereIn('status', ['accepted', 'preparing', 'ready'])->count();

        $guestOrders = (clone $todayOrders)->where('customer_type', 'guest')->count();
        $loggedInOrders = (clone $todayOrders)->where('customer_type', 'member')->count();

        $popularItems = OrderItem::select('snapshot_name', DB::raw('SUM(quantity) as units'))
            ->groupBy('snapshot_name')
            ->orderByDesc('units')
            ->limit(5)
            ->get();

        $pointsRedeemedToday = LoyaltyPointTransaction::where('type', 'redeem')
            ->whereDate('created_at', $today)
            ->sum(DB::raw('ABS(points)'));

        $memberCount = CustomerProfile::count();

        $needsAttention = Order::with('table', 'customer')
            ->needsAttention()
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
