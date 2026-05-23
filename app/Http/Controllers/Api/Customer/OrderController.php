<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use App\Services\OrderFormatHelper;
use App\Services\OrderPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function store(Request $request, string $tenant_slug, string $qr_token, OrderPricingService $pricing): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instruction' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $table = DB::table('tables')->where('qr_token', $qr_token)->where('status', 'active')->first();
        if (! $table) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $settings = DB::table('settings')->first();

        $authed = $request->user();
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

        $orderId = (string) Str::uuid();
        $orderNumber = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        $approvalStatus = $customerType === 'guest' ? 'approval_pending' : 'not_required';

        DB::transaction(function () use ($orderId, $orderNumber, $table, $price, $settings, $customerType, $checkoutType, $customerId, $approvalStatus) {
            DB::table('orders')->insert([
                'id' => $orderId,
                'order_number' => $orderNumber,
                'table_id' => $table->id,
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
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($price['lines'] as $line) {
                DB::table('order_items')->insert([
                    'id' => (string) Str::uuid(),
                    'order_id' => $orderId,
                    'menu_item_id' => $line['menu_item_id'],
                    'snapshot_name' => $line['snapshot_name'],
                    'snapshot_price' => $line['snapshot_price'],
                    'quantity' => $line['quantity'],
                    'subtotal_amount' => $line['subtotal_amount'],
                    'instruction' => $line['instruction'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json([
            'status' => 201,
            'data' => [
                'order' => DB::table('orders')->where('id', $orderId)->first(),
                'items' => DB::table('order_items')->where('order_id', $orderId)->get(),
            ],
        ], 201);
    }

    public function updateItems(Request $request, string $tenant_slug, string $orderId, OrderPricingService $pricing): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        if ($order->payment_status !== 'unpaid' || in_array($order->status, ['completed', 'cancelled', 'expired'])) {
            return response()->json([
                'status' => 422,
                'message' => 'This order can no longer be updated.',
                'can_create_new_order' => true,
            ], 422);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.instruction' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $existingItems = DB::table('order_items')->where('order_id', $orderId)->get()->keyBy('menu_item_id');

        $newItemIds = collect($data['items'])->pluck('menu_item_id')->unique()->values()->all();
        $newMenuItems = DB::table('menu_items')
            ->whereIn('id', $newItemIds)
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

        $profile = DB::table('profile')->first();
        $serviceRate = $profile ? (float) ($profile->service_charge_rate ?? 0) : 0.0;
        $taxRate = $profile ? (float) ($profile->tax_rate ?? 0) : 0.0;
        $serviceCharge = round($subtotal * $serviceRate / 100, 2);
        $tax = round($subtotal * $taxRate / 100, 2);
        $grossTotal = round($subtotal + $serviceCharge + $tax, 2);

        DB::transaction(function () use ($orderId, $merged, $subtotal, $serviceCharge, $tax, $grossTotal) {
            DB::table('order_items')->where('order_id', $orderId)->delete();

            foreach ($merged as $line) {
                DB::table('order_items')->insert([
                    'id' => (string) Str::uuid(),
                    'order_id' => $orderId,
                    'menu_item_id' => $line['menu_item_id'],
                    'snapshot_name' => $line['snapshot_name'],
                    'snapshot_price' => $line['snapshot_price'],
                    'quantity' => $line['quantity'],
                    'subtotal_amount' => $line['subtotal_amount'],
                    'instruction' => $line['instruction'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('orders')->where('id', $orderId)->update([
                'subtotal_amount' => $subtotal,
                'service_charge_amount' => $serviceCharge,
                'tax_amount' => $tax,
                'gross_total_amount' => $grossTotal,
                'payable_amount' => $grossTotal,
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 200,
            'data' => [
                'order' => DB::table('orders')->where('id', $orderId)->first(),
                'items' => DB::table('order_items')->where('order_id', $orderId)->get(),
            ],
        ]);
    }

    public function applyPoints(Request $request, string $tenant_slug, string $orderId, LoyaltyService $loyalty): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'string'],
            'redeem_points' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order || $order->payment_status !== 'unpaid') {
            return response()->json(['status' => 422, 'message' => 'Order not eligible for point redemption.'], 422);
        }

        $customer = DB::table('customer_profiles')->where('customer_id', $data['customer_id'])->first();
        if (! $customer || $customer->total_points < $data['redeem_points']) {
            return response()->json(['status' => 422, 'message' => 'Not enough points.'], 422);
        }

        $discount = $loyalty->pointDiscountAmount($data['redeem_points']);
        $payable = max(0, (float) $order->gross_total_amount - $discount);

        DB::table('orders')->where('id', $orderId)->update([
            'customer_id' => $data['customer_id'],
            'customer_type' => 'member',
            'redeemed_points' => $data['redeem_points'],
            'point_discount_amount' => $discount,
            'payable_amount' => $payable,
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => DB::table('orders')->where('id', $orderId)->first(),
        ]);
    }

    public function status(string $tenant_slug, string $orderId): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['status' => 404, 'message' => 'Order not found.'], 404);
        }

        $formatted = OrderFormatHelper::formatWithItems($order);

        $canUpdate = $order->payment_status === 'unpaid'
            && ! in_array($order->status, ['completed', 'cancelled', 'expired']);

        $formatted['can_update_before_payment'] = $canUpdate;

        $pendingPaymentIds = DB::table('payments')
            ->where('order_id', $orderId)
            ->where('status', 'pending')
            ->pluck('id');

        $latestSession = $pendingPaymentIds->isNotEmpty()
            ? DB::table('payment_sessions')
                ->whereIn('payment_id', $pendingPaymentIds)
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
