<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        $type = $request->query('type', 'all');
        $sort = $request->query('sort', 'latest_visit');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        $query = DB::table('customers')
            ->leftJoin('customer_profiles', 'customers.id', '=', 'customer_profiles.customer_id')
            ->select(
                'customers.id',
                'customers.name',
                'customers.email',
                'customers.phone',
                'customers.status',
                'customers.created_at',
                'customer_profiles.total_points',
                'customer_profiles.membership_level',
            )
            ->when($search, function ($q) use ($search) {
                $like = '%'.strtolower($search).'%';
                $q->where(function ($q2) use ($like) {
                    $q2->whereRaw('LOWER(customers.name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(customers.phone) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(customers.email) LIKE ?', [$like]);
                });
            });

        if ($type === 'members') {
            $query->whereNotNull('customer_profiles.id');
        } elseif ($type === 'guests') {
            $query->whereNull('customer_profiles.id');
        }

        $customers = $query->get();

        $enriched = $customers->map(function ($c) {
            $orders = DB::table('orders')
                ->where('customer_id', $c->id)
                ->orderByDesc('created_at')
                ->get();

            $totalOrders = $orders->count();
            $totalSpent = (float) $orders->where('payment_status', 'paid')->sum('payable_amount');
            $lastVisit = $orders->first()?->created_at;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'status' => $c->status,
                'total_points' => (int) ($c->total_points ?? 0),
                'membership_level' => $c->membership_level,
                'total_orders' => $totalOrders,
                'total_spent' => $totalSpent,
                'last_visit_at' => $lastVisit,
                'created_at' => $c->created_at,
            ];
        });

        $sorted = match ($sort) {
            'highest_points' => $enriched->sortByDesc('total_points')->values(),
            'most_orders' => $enriched->sortByDesc('total_orders')->values(),
            default => $enriched->sortByDesc(fn ($c) => $c['last_visit_at'] ?? $c['created_at'])->values(),
        };

        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $safePage = min($page, $lastPage);
        $items = $sorted->slice(($safePage - 1) * $perPage, $perPage)->values();

        $repeatIds = DB::table('orders')
            ->select('customer_id')
            ->whereNotNull('customer_id')
            ->where('customer_type', 'member')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('customer_id')
            ->count();

        $summary = [
            'total_customers' => DB::table('customers')->count(),
            'total_members' => DB::table('customer_profiles')->count(),
            'total_points' => (int) DB::table('customer_profiles')->sum('total_points'),
            'repeat_customers' => $repeatIds,
        ];

        return response()->json([
            'status' => 200,
            'data' => $items,
            'meta' => [
                'current_page' => $safePage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? ($safePage - 1) * $perPage + 1 : 0,
                'to' => min($safePage * $perPage, $total),
            ],
            'summary' => $summary,
        ]);
    }

    public function show(string $tenant_slug, string $customerId): JsonResponse
    {
        $customer = DB::table('customers')
            ->leftJoin('customer_profiles', 'customers.id', '=', 'customer_profiles.customer_id')
            ->where('customers.id', $customerId)
            ->select(
                'customers.id',
                'customers.name',
                'customers.email',
                'customers.phone',
                'customers.status',
                'customers.created_at',
                'customer_profiles.total_points',
                'customer_profiles.membership_level',
            )
            ->first();

        if (! $customer) {
            return response()->json(['status' => 404, 'message' => 'Customer not found.'], 404);
        }

        $orders = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($o) {
                $table = $o->table_id ? DB::table('tables')->where('id', $o->table_id)->first() : null;

                return [
                    'id' => $o->id,
                    'order_code' => $o->order_number,
                    'table_name' => $table?->table_name ?? 'No table',
                    'payable_amount' => (float) $o->payable_amount,
                    'payment_status' => $o->payment_status,
                    'order_status' => $o->status,
                    'created_at' => $o->created_at,
                ];
            })->values()->toArray();

        $totalOrders = DB::table('orders')->where('customer_id', $customer->id)->count();
        $totalSpent = (float) DB::table('orders')
            ->where('customer_id', $customer->id)
            ->where('payment_status', 'paid')
            ->sum('payable_amount');
        $lastVisit = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->value('created_at');

        $pointsActivity = DB::table('loyalty_point_transactions')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'points' => (int) $t->points,
                'description' => $t->description,
                'created_at' => $t->created_at,
            ])->values()->toArray();

        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status,
                'total_points' => (int) ($customer->total_points ?? 0),
                'membership_level' => $customer->membership_level,
                'total_orders' => $totalOrders,
                'total_spent' => $totalSpent,
                'last_visit_at' => $lastVisit,
                'created_at' => $customer->created_at,
                'recent_orders' => $orders,
                'points_activity' => $pointsActivity,
            ],
        ]);
    }
}
