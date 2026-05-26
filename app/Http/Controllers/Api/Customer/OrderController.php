<?php

namespace App\Http\Controllers\Api\Customer;

use App\Actions\ResolveCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\ApplyPointsRequest;
use App\Http\Requests\Api\Customer\StoreOrderRequest;
use App\Http\Requests\Api\Customer\UpdateOrderItemsRequest;
use App\Models\CustomerProfile;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentSession;
use App\Models\Profile;
use App\Models\Settings;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\LoyaltyService;
use App\Services\OrderBroadcastService;
use App\Services\OrderFormatHelper;
use App\Services\OrderPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private readonly ResolveCustomerAction $resolveCustomer,
        private readonly OrderBroadcastService $broadcaster,
    ) {}

    public function store(StoreOrderRequest $request, string $tenant_slug, string $qr_token, OrderPricingService $pricing): JsonResponse
    {
        $data = $request->validated();

        $table = Table::where(function ($q) use ($qr_token) {
            $q->where('qr_token', $qr_token)
                ->orWhere('public_code', $qr_token);
        })
            ->where('status', 'active')
            ->first();
        if (! $table) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $settings = Settings::first();
        $sessionEnabled = $settings ? (bool) $settings->table_session_enabled : true;

        $tableSessionId = null;

        if ($sessionEnabled) {
            $sessionToken = $data['table_session_token'] ?? null;

            if (! $sessionToken) {
                return response()->json([
                    'status' => 422,
                    'error' => 'session_expired',
                    'message' => 'This table session expired. Please scan the QR again or ask staff.',
                ], 422);
            }

            $session = TableSession::where('token', $sessionToken)
                ->where('table_id', $table->id)
                ->first();

            if (! $session) {
                return response()->json([
                    'status' => 422,
                    'error' => 'session_expired',
                    'message' => 'This table session expired. Please scan the QR again or ask staff.',
                ], 422);
            }

            if ($session->status !== 'active') {
                $errorCode = $session->status === 'blocked' ? 'session_blocked' : 'session_expired';

                return response()->json([
                    'status' => 422,
                    'error' => $errorCode,
                    'message' => $session->status === 'blocked'
                        ? 'This ordering session is no longer active. Please ask staff for help.'
                        : 'This table session expired. Please scan the QR again or ask staff.',
                ], 422);
            }

            if ($session->expires_at && now()->gt($session->expires_at)) {
                TableSession::where('id', $session->id)->update(['status' => 'expired']);

                return response()->json([
                    'status' => 422,
                    'error' => 'session_expired',
                    'message' => 'This table session expired. Please scan the QR again or ask staff.',
                ], 422);
            }

            $sessionTable = Table::where('id', $session->table_id)->first();
            if (! $sessionTable || ! $sessionTable->ordering_enabled) {
                return response()->json([
                    'status' => 403,
                    'error' => 'ordering_disabled',
                    'message' => 'Ordering is currently disabled for this table.',
                ], 403);
            }

            $tableSessionId = $session->id;

            TableSession::where('id', $session->id)->update([
                'last_activity_at' => now(),
            ]);
        }

        $authed = $this->resolveCustomer->execute($request);
        $customerId = null;
        $customerType = 'guest';

        if ($authed) {
            $customerId = $authed->id;
            $customerType = 'member';
        }

        if (! $authed && ! $settings->allow_guest_order) {
            return response()->json(['status' => 403, 'message' => 'Guest ordering disabled.'], 403);
        }

        $price = $pricing->calculate($data['items']);
        if ($price['lines']->isEmpty()) {
            return response()->json(['status' => 422, 'message' => 'No valid menu items.'], 422);
        }

        $checkoutType = $authed && $settings->allow_member_self_checkout ? 'self_checkout' : 'cashier_checkout';

        $orderNumber = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        $approvalStatus = $customerType === 'guest' ? 'approval_pending' : 'not_required';

        $order = DB::transaction(function () use ($orderNumber, $table, $price, $settings, $customerType, $checkoutType, $customerId, $approvalStatus, $tableSessionId) {
            $order = Order::create([
                'order_number' => $orderNumber,
                'public_access_token' => Str::random(48),
                'table_id' => $table->id,
                'table_session_id' => $tableSessionId,
                'customer_id' => $customerId,
                'customer_type' => $customerType,
                'checkout_type' => $checkoutType,
                'payment_timing' => $settings->payment_timing,
                'status' => $settings->payment_timing === 'pay_before_prepare' ? 'pending_payment' : 'submitted',
                'payment_status' => 'unpaid',
                'approval_status' => $approvalStatus,
                'subtotal_amount' => $price['subtotal'],
                'service_charge_amount' => $price['service_charge'],
                'tax_amount' => $price['tax'],
                'gross_total_amount' => $price['gross_total'],
                'redeemed_points' => 0,
                'point_discount_amount' => 0,
                'payable_amount' => $price['gross_total'],
                'earned_points' => 0,
            ]);

            foreach ($price['lines'] as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $line['menu_item_id'],
                    'snapshot_name' => $line['snapshot_name'],
                    'snapshot_price' => $line['snapshot_price'],
                    'quantity' => $line['quantity'],
                    'subtotal_amount' => $line['subtotal_amount'],
                    'instruction' => $line['instruction'],
                ]);
            }

            return $order;
        });

        return response()->json([
            'status' => 201,
            'data' => [
                'order' => Order::where('id', $order->id)->first(),
                'items' => OrderItem::where('order_id', $order->id)->get(),
                'access_token' => $order->public_access_token,
            ],
        ], 201);
    }

    public function updateItems(UpdateOrderItemsRequest $request, string $tenant_slug, string $orderId, OrderPricingService $pricing): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        $accessToken = $request->header('X-Order-Access-Token');
        if ($accessToken && $order->public_access_token !== $accessToken) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        if ($order->payment_status !== 'unpaid' || in_array($order->status, ['completed', 'cancelled', 'expired'])) {
            return response()->json([
                'status' => 422,
                'message' => 'This order can no longer be updated.',
                'can_create_new_order' => true,
            ], 422);
        }

        $data = $request->validated();

        $existingItems = OrderItem::where('order_id', $orderId)->get()->keyBy('menu_item_id');

        $newItemIds = collect($data['items'])->pluck('menu_item_id')->unique()->values()->all();
        $newMenuItems = MenuItem::whereIn('id', $newItemIds)
            ->where('status', 'active')
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        $merged = $existingItems->mapWithKeys(function ($item) {
            return [$item->menu_item_id => [
                'menu_item_id' => $item->menu_item_id,
                'snapshot_name' => $item->snapshot_name,
                'snapshot_price' => (float) $item->snapshot_price,
                'quantity' => (int) $item->quantity,
                'subtotal_amount' => (float) $item->subtotal_amount,
                'instruction' => $item->instruction,
            ]];
        })->toArray();

        foreach ($data['items'] as $newItem) {
            $id = $newItem['menu_item_id'];
            $menuItem = $newMenuItems->get($id);

            if (isset($merged[$id])) {
                $merged[$id]['quantity'] += (int) $newItem['quantity'];
                $merged[$id]['subtotal_amount'] = $merged[$id]['snapshot_price'] * $merged[$id]['quantity'];
                if (! empty($newItem['instruction'])) {
                    $merged[$id]['instruction'] = $newItem['instruction'];
                }
            } elseif ($menuItem) {
                $qty = (int) $newItem['quantity'];
                $unit = (float) $menuItem->price;
                $merged[$id] = [
                    'menu_item_id' => $menuItem->id,
                    'snapshot_name' => $menuItem->name,
                    'snapshot_price' => $unit,
                    'quantity' => $qty,
                    'subtotal_amount' => round($unit * $qty, 2),
                    'instruction' => $newItem['instruction'] ?? null,
                ];
            }
        }

        if (empty($merged)) {
            return response()->json(['status' => 422, 'message' => 'No valid menu items.'], 422);
        }

        $subtotal = (float) collect($merged)->sum('subtotal_amount');

        $profile = Profile::first();
        $serviceRate = $profile ? (float) ($profile->service_charge_rate ?? 0) : 0.0;
        $taxRate = $profile ? (float) ($profile->tax_rate ?? 0) : 0.0;
        $serviceCharge = round($subtotal * $serviceRate / 100, 2);
        $tax = round($subtotal * $taxRate / 100, 2);
        $grossTotal = round($subtotal + $serviceCharge + $tax, 2);

        DB::transaction(function () use ($orderId, $merged, $subtotal, $serviceCharge, $tax, $grossTotal) {
            OrderItem::where('order_id', $orderId)->delete();

            foreach ($merged as $line) {
                OrderItem::create([
                    'order_id' => $orderId,
                    'menu_item_id' => $line['menu_item_id'],
                    'snapshot_name' => $line['snapshot_name'],
                    'snapshot_price' => $line['snapshot_price'],
                    'quantity' => $line['quantity'],
                    'subtotal_amount' => $line['subtotal_amount'],
                    'instruction' => $line['instruction'],
                ]);
            }

            Order::where('id', $orderId)->update([
                'subtotal_amount' => $subtotal,
                'service_charge_amount' => $serviceCharge,
                'tax_amount' => $tax,
                'gross_total_amount' => $grossTotal,
                'payable_amount' => $grossTotal,
            ]);
        });

        $this->broadcaster->broadcastUpdate($order->fresh(), 'items_updated');

        return response()->json([
            'status' => 200,
            'data' => [
                'order' => Order::where('id', $orderId)->first(),
                'items' => OrderItem::where('order_id', $orderId)->get(),
            ],
        ]);
    }

    public function applyPoints(ApplyPointsRequest $request, string $tenant_slug, string $orderId, LoyaltyService $loyalty): JsonResponse
    {
        $data = $request->validated();

        $order = Order::where('id', $orderId)->first();
        if (! $order || $order->payment_status !== 'unpaid') {
            return response()->json(['status' => 422, 'message' => 'Order not eligible for point redemption.'], 422);
        }

        $customer = CustomerProfile::where('customer_id', $data['customer_id'])->first();
        if (! $customer || $customer->total_points < $data['redeem_points']) {
            return response()->json(['status' => 422, 'message' => 'Not enough points.'], 422);
        }

        $discount = $loyalty->pointDiscountAmount($data['redeem_points']);
        $payable = max(0, (float) $order->gross_total_amount - $discount);

        $order->update([
            'customer_id' => $data['customer_id'],
            'customer_type' => 'member',
            'redeemed_points' => $data['redeem_points'],
            'point_discount_amount' => $discount,
            'payable_amount' => $payable,
        ]);

        return response()->json([
            'status' => 200,
            'data' => Order::where('id', $orderId)->first(),
        ]);
    }

    public function status(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        $accessToken = request()->header('X-Order-Access-Token');
        if ($accessToken && $order->public_access_token !== $accessToken) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        $formatted = OrderFormatHelper::formatWithItems($order);

        $canUpdate = $order->payment_status === 'unpaid'
            && ! in_array($order->status, ['completed', 'cancelled', 'expired']);

        $formatted['can_update_before_payment'] = $canUpdate;

        $pendingPaymentIds = Payment::where('order_id', $orderId)
            ->where('status', 'pending')
            ->pluck('id');

        $latestSession = $pendingPaymentIds->isNotEmpty()
            ? PaymentSession::whereIn('payment_id', $pendingPaymentIds)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->first()
            : null;

        if ($latestSession) {
            $formatted['qr_payload'] = $latestSession->qr_payload;
            $formatted['payment_session_id'] = $latestSession->id;
        }

        return response()->json([
            'status' => 200,
            'data' => $formatted,
        ]);
    }
}
