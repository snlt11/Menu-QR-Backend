<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
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

        return response()->json([
            'status' => 200,
            'data' => [
                'today' => [
                    'sales_amount' => (float) (clone $todayPaid)->sum('payable_amount'),
                    'orders_count' => (clone $todayOrders)->count(),
                    'paid_orders_count' => (clone $todayPaid)->count(),
                ],
                'unpaid_bills' => $unpaidBills,
                'popular_items' => $popularItems,
                'points_redeemed_today' => (int) $pointsRedeemedToday,
                'member_count' => DB::table('customer_profiles')->count(),
            ],
        ]);
    }
}
