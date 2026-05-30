<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\ReportRequest;
use App\Models\Customer;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderFormatHelper;
use App\Services\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportExportService $exportService,
    ) {}

    private static function formatMoney(float $amount): string
    {
        return 'MMK '.number_format($amount, 0, '.', ',');
    }

    private static function formatPaymentMethod(string $method): string
    {
        $map = [
            'qr_payment' => 'QR Payment',
            'cash' => 'Cash',
            'demo_qr' => 'QR Payment',
            'unpaid' => 'Unpaid',
        ];

        return $map[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    private static function formatStatus(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    private static function formatDate(string $isoDate): string
    {
        try {
            return Carbon::parse($isoDate)->format('d M Y, h:i A');
        } catch (\Throwable) {
            return $isoDate;
        }
    }

    private static function formatDateRangeLabel(string $from, string $to): string
    {
        try {
            $fromFormatted = Carbon::parse($from)->format('d M Y');
            $toFormatted = Carbon::parse($to)->format('d M Y');

            return "Date range: {$fromFormatted} - {$toFormatted}";
        } catch (\Throwable) {
            return "Date range: {$from} - {$to}";
        }
    }

    private function dateFilename(string $from, string $to): string
    {
        return "{$from}-to-{$to}";
    }

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

        $memberCount = Customer::count();

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

    public function summary(ReportRequest $request, string $tenant_slug): JsonResponse
    {
        $from = $request->input('from', today()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $query = Order::whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to);

        $paidQuery = (clone $query)->where('orders.payment_status', 'paid');
        $unpaidQuery = (clone $query)->whereIn('orders.payment_status', ['unpaid', 'pending']);
        $rejectedQuery = (clone $query)->where('orders.approval_status', 'rejected');

        $pointsRedeemed = LoyaltyPointTransaction::where('type', 'redeem')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum(DB::raw('ABS(points)'));

        $paymentBreakdown = (clone $paidQuery)
            ->join('payments', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'paid')
            ->select('payments.method', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('payments.method')
            ->pluck('total', 'payments.method')
            ->toArray();

        $totalSales = (float) (clone $paidQuery)->sum('orders.payable_amount');
        $totalOrders = (clone $query)->count();
        $paidOrders = (clone $paidQuery)->count();
        $unpaidBills = (clone $unpaidQuery)->count();
        $rejectedOrders = (clone $rejectedQuery)->count();
        $avgOrderValue = $paidOrders > 0 ? round($totalSales / $paidOrders, 2) : 0;

        return response()->json([
            'status' => 200,
            'data' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'paid_orders' => $paidOrders,
                'unpaid_bills' => $unpaidBills,
                'rejected_orders' => $rejectedOrders,
                'average_order_value' => $avgOrderValue,
                'points_redeemed' => (int) $pointsRedeemed,
                'payment_breakdown' => $paymentBreakdown,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function orders(ReportRequest $request, string $tenant_slug): JsonResponse
    {
        $from = $request->input('from', today()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $query = Order::with(['table', 'customer'])
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to);

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->input('approval_status'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('table', fn ($tq) => $tq->where('table_name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $data = $orders->map(function (Order $order) {
            $customerName = 'Guest';
            $customerType = 'Guest';
            if ($order->customer_type === 'member' && $order->customer) {
                $customerName = $order->customer->name;
                $customerType = 'Member';
            }

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'table_name' => $order->table?->table_name ?? 'No table',
                'customer_name' => $customerName,
                'customer_type' => $customerType,
                'payment_status' => $order->payment_status,
                'approval_status' => $order->approval_status,
                'status' => $order->status,
                'payable_amount' => (float) $order->payable_amount,
                'created_at' => $order->created_at->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'status' => 200,
            'data' => $data,
        ]);
    }

    public function menuItemSales(ReportRequest $request, string $tenant_slug): JsonResponse
    {
        $from = $request->input('from', today()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to);

        $items = $query
            ->leftJoin('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->select(
                'order_items.snapshot_name as item_name',
                'menu_categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.subtotal_amount) as total_amount'),
            )
            ->groupBy('order_items.snapshot_name', 'menu_categories.name')
            ->orderByDesc('quantity_sold')
            ->get()
            ->map(fn ($item) => [
                'item_name' => $item->item_name,
                'category_name' => $item->category_name ?? 'Uncategorized',
                'quantity_sold' => (int) $item->quantity_sold,
                'total_amount' => (float) $item->total_amount,
            ]);

        return response()->json([
            'status' => 200,
            'data' => $items,
        ]);
    }

    public function exportSalesCsv(ReportRequest $request, string $tenant_slug)
    {
        return $this->exportService->csv(...$this->buildSalesExport($request, $tenant_slug));
    }

    public function exportSalesExcel(ReportRequest $request, string $tenant_slug)
    {
        [$headers, $rows, $filename, $title, $dateRange] = $this->buildSalesExport($request, $tenant_slug);

        return $this->exportService->excel($headers, $rows, $filename, $title, $dateRange, 'Sales Report');
    }

    public function exportOrdersCsv(ReportRequest $request, string $tenant_slug)
    {
        return $this->exportService->csv(...$this->buildOrdersExport($request, $tenant_slug));
    }

    public function exportOrdersExcel(ReportRequest $request, string $tenant_slug)
    {
        [$headers, $rows, $filename, $title, $dateRange] = $this->buildOrdersExport($request, $tenant_slug);

        return $this->exportService->excel($headers, $rows, $filename, $title, $dateRange, 'Orders Report');
    }

    public function exportMenuItemSalesCsv(ReportRequest $request, string $tenant_slug)
    {
        return $this->exportService->csv(...$this->buildMenuItemSalesExport($request, $tenant_slug));
    }

    public function exportMenuItemSalesExcel(ReportRequest $request, string $tenant_slug)
    {
        [$headers, $rows, $filename, $title, $dateRange] = $this->buildMenuItemSalesExport($request, $tenant_slug);

        return $this->exportService->excel($headers, $rows, $filename, $title, $dateRange, 'Menu Item Sales');
    }

    public function exportOverallExcel(ReportRequest $request, string $tenant_slug)
    {
        $tenantName = tenant()->name ?? $tenant_slug;
        $from = $request->input('from', today()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $summaryData = $this->summary($request, $tenant_slug)->getData(true)['data'];
        $ordersData = $this->orders($request, $tenant_slug)->getData(true)['data'];
        $menuItemData = $this->menuItemSales($request, $tenant_slug)->getData(true)['data'];

        $overviewRows = [
            ['Total sales', self::formatMoney($summaryData['total_sales'])],
            ['Total orders', (string) $summaryData['total_orders']],
            ['Paid orders', (string) $summaryData['paid_orders']],
            ['Unpaid bills', (string) $summaryData['unpaid_bills']],
            ['Rejected orders', (string) $summaryData['rejected_orders']],
            ['Average order value', self::formatMoney($summaryData['average_order_value'])],
            ['Points redeemed', (string) $summaryData['points_redeemed']],
        ];

        foreach ($summaryData['payment_breakdown'] as $method => $total) {
            $overviewRows[] = ['Payment ('.self::formatPaymentMethod($method).')', self::formatMoney($total)];
        }

        $ordersRows = array_map(function ($order) {
            return [
                $order['order_number'],
                $order['table_name'],
                $order['customer_name'],
                $order['customer_type'],
                self::formatStatus($order['payment_status']),
                self::formatStatus($order['approval_status']),
                self::formatStatus($order['status']),
                self::formatMoney($order['payable_amount']),
                self::formatDate($order['created_at']),
            ];
        }, $ordersData);

        $menuRows = array_map(function ($item) {
            return [
                $item['item_name'],
                $item['category_name'],
                (string) $item['quantity_sold'],
                self::formatMoney($item['total_amount']),
            ];
        }, $menuItemData);

        $sheets = [
            [
                'title' => 'Menu QR - Overall Report',
                'tenantName' => $tenantName,
                'dateRange' => self::formatDateRangeLabel($from, $to),
                'headers' => ['Metric', 'Value'],
                'rows' => $overviewRows,
                'sheetName' => 'Overview',
            ],
            [
                'title' => 'Menu QR - Orders Report',
                'tenantName' => $tenantName,
                'dateRange' => self::formatDateRangeLabel($from, $to),
                'headers' => ['Order #', 'Table', 'Customer', 'Customer Type', 'Payment', 'Approval', 'Status', 'Total Amount', 'Date'],
                'rows' => $ordersRows,
                'sheetName' => 'Orders Report',
            ],
            [
                'title' => 'Menu QR - Menu Item Sales',
                'tenantName' => $tenantName,
                'dateRange' => self::formatDateRangeLabel($from, $to),
                'headers' => ['Item', 'Category', 'Quantity Sold', 'Total Amount'],
                'rows' => $menuRows,
                'sheetName' => 'Menu Item Sales',
            ],
        ];

        $filename = "overall-report-{$tenant_slug}-{$this->dateFilename($from, $to)}";

        return $this->exportService->multiSheetExcel($sheets, $filename);
    }

    private function buildSalesExport(ReportRequest $request, string $tenant_slug): array
    {
        $data = $this->summary($request, $tenant_slug)->getData(true)['data'];
        $from = $data['from'];
        $to = $data['to'];

        $rows = [
            ['Total sales', self::formatMoney($data['total_sales'])],
            ['Total orders', (string) $data['total_orders']],
            ['Paid orders', (string) $data['paid_orders']],
            ['Unpaid bills', (string) $data['unpaid_bills']],
            ['Rejected orders', (string) $data['rejected_orders']],
            ['Average order value', self::formatMoney($data['average_order_value'])],
            ['Points redeemed', (string) $data['points_redeemed']],
        ];

        foreach ($data['payment_breakdown'] as $method => $total) {
            $rows[] = ['Payment ('.self::formatPaymentMethod($method).')', self::formatMoney($total)];
        }

        return [
            ['Metric', 'Value'],
            $rows,
            "sales-report-{$tenant_slug}-{$this->dateFilename($from, $to)}",
            'Menu QR - Sales Report',
            self::formatDateRangeLabel($from, $to),
        ];
    }

    private function buildOrdersExport(ReportRequest $request, string $tenant_slug): array
    {
        $data = $this->orders($request, $tenant_slug)->getData(true)['data'];
        $from = $request->input('from', today()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $rows = array_map(function ($order) {
            return [
                $order['order_number'],
                $order['table_name'],
                $order['customer_name'],
                $order['customer_type'],
                self::formatStatus($order['payment_status']),
                self::formatStatus($order['approval_status']),
                self::formatStatus($order['status']),
                self::formatMoney($order['payable_amount']),
                self::formatDate($order['created_at']),
            ];
        }, $data);

        return [
            ['Order #', 'Table', 'Customer', 'Customer Type', 'Payment', 'Approval', 'Status', 'Total Amount', 'Date'],
            $rows,
            "orders-report-{$tenant_slug}-{$this->dateFilename($from, $to)}",
            'Menu QR - Orders Report',
            self::formatDateRangeLabel($from, $to),
        ];
    }

    private function buildMenuItemSalesExport(ReportRequest $request, string $tenant_slug): array
    {
        $data = $this->menuItemSales($request, $tenant_slug)->getData(true)['data'];
        $from = $request->input('from', today()->toDateString());
        $to = $request->input('to', today()->toDateString());

        $rows = array_map(function ($item) {
            return [
                $item['item_name'],
                $item['category_name'],
                (string) $item['quantity_sold'],
                self::formatMoney($item['total_amount']),
            ];
        }, $data);

        return [
            ['Item', 'Category', 'Quantity Sold', 'Total Amount'],
            $rows,
            "menu-items-report-{$tenant_slug}-{$this->dateFilename($from, $to)}",
            'Menu QR - Menu Item Sales Report',
            self::formatDateRangeLabel($from, $to),
        ];
    }
}
