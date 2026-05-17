<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
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
            'customer_id' => ['sometimes', 'nullable', 'string'],
        ]);

        $table = DB::table('shop_tables')->where('qr_token', $qr_token)->where('status', 'active')->first();
        if (! $table) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $settings = DB::table('shop_settings')->first();
        $isMember = filled($data['customer_id'] ?? null);
        $customerType = $isMember ? 'member' : 'guest';

        if (! $isMember && ! $settings->allow_guest_order) {
            return response()->json(['status' => 403, 'message' => 'Guest ordering disabled.'], 403);
        }

        $price = $pricing->calculate($data['items']);
        if ($price['lines']->isEmpty()) {
            return response()->json(['status' => 422, 'message' => 'No valid menu items.'], 422);
        }

        $checkoutType = $isMember && $settings->allow_member_self_checkout ? 'self_checkout' : 'cashier_checkout';

        $orderId = (string) Str::uuid();
        $orderNumber = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        DB::transaction(function () use ($orderId, $orderNumber, $table, $data, $price, $settings, $customerType, $checkoutType, $isMember) {
            DB::table('orders')->insert([
                'id' => $orderId,
                'order_number' => $orderNumber,
                'table_id' => $table->id,
                'customer_id' => $isMember ? $data['customer_id'] : null,
                'customer_type' => $customerType,
                'checkout_type' => $checkoutType,
                'payment_timing' => $settings->payment_timing,
                'status' => $settings->payment_timing === 'pay_before_prepare' ? 'pending_payment' : 'submitted',
                'payment_status' => 'unpaid',
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

        return response()->json([
            'status' => 200,
            'data' => [
                'order' => $order,
                'items' => DB::table('order_items')->where('order_id', $orderId)->get(),
            ],
        ]);
    }
}
