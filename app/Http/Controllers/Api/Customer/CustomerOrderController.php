<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\OrderFormatHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        $query = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        $total = $query->count();

        $orders = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn ($order) => OrderFormatHelper::format($order))
            ->values()
            ->toArray();

        return response()->json([
            'status' => 200,
            'data' => $orders,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function points(Request $request): JsonResponse
    {
        $customer = $request->user();

        $profile = DB::table('customer_profiles')
            ->where('customer_id', $customer->id)
            ->first();

        $settings = DB::table('settings')->first();
        $shopProfile = DB::table('profile')->first();

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $txQuery = DB::table('loyalty_point_transactions')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        $totalTx = $txQuery->count();

        $transactions = $txQuery
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'points' => (int) $t->points,
                'description' => $t->description,
                'created_at' => $t->created_at,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'status' => 200,
            'data' => [
                'balance' => $profile ? (int) $profile->total_points : 0,
                'membership_level' => $profile ? $profile->membership_level : 'basic',
                'currency' => $shopProfile ? $shopProfile->currency : 'MMK',
                'earning_rule' => $settings && $settings->points_enabled ? [
                    'enabled' => true,
                    'earn_rate_amount' => (int) $settings->earn_rate_amount,
                    'earn_rate_points' => (int) $settings->earn_rate_points,
                ] : ['enabled' => false],
                'transactions' => $transactions,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalTx,
                    'last_page' => max(1, (int) ceil($totalTx / $perPage)),
                ],
            ],
        ]);
    }

    public function claim(Request $request, string $tenant_slug, string $orderId): JsonResponse
    {
        $customer = $request->user();

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->customer_id === $customer->id) {
            return response()->json([
                'status' => 200,
                'data' => OrderFormatHelper::format(
                    DB::table('orders')->where('id', $orderId)->first()
                ),
            ]);
        }

        if ($order->customer_id !== null) {
            return response()->json(['status' => 403, 'message' => 'Order belongs to another customer.'], 403);
        }

        DB::table('orders')->where('id', $orderId)->update([
            'customer_id' => $customer->id,
            'customer_type' => 'member',
            'approval_status' => $order->approval_status === 'approval_pending' ? 'not_required' : $order->approval_status,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => OrderFormatHelper::format(
                DB::table('orders')->where('id', $orderId)->first()
            ),
        ]);
    }
}
