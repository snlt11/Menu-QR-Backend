<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderFormatHelper
{
    public static function format(Order $order): array
    {
        $tableName = null;
        $tableCode = null;
        if ($order->relationLoaded('table') && $order->table) {
            $tableName = $order->table->table_name ?? ($order->table->table_number ? "Table {$order->table->table_number}" : null);
            $tableCode = $order->table->table_number;
        } elseif ($order->table_id) {
            $table = DB::table('tables')->where('id', $order->table_id)->first();
            $tableName = $table?->table_name ?? ($table?->table_number ? "Table {$table->table_number}" : null);
            $tableCode = $table?->table_number;
        }

        $customerName = null;
        if ($order->relationLoaded('customer') && $order->customer) {
            $customerName = $order->customer->name;
        } elseif ($order->customer_id) {
            $customer = DB::table('customers')->where('id', $order->customer_id)->first();
            $customerName = $customer?->name;
        }

        $itemCount = 0;
        $itemsPreview = [];
        if ($order->relationLoaded('items') && $order->items->isNotEmpty()) {
            $itemCount = $order->items->sum(fn ($i) => (int) $i->quantity);
            $itemsPreview = $order->items->take(5)->map(fn ($i) => [
                'name' => $i->snapshot_name,
                'quantity' => (int) $i->quantity,
            ])->values()->toArray();
        } else {
            $items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->orderBy('created_at')
                ->get();

            $itemCount = $items->sum(fn ($i) => (int) $i->quantity);
            $itemsPreview = $items->take(5)->map(fn ($i) => [
                'name' => $i->snapshot_name,
                'quantity' => (int) $i->quantity,
            ])->values()->toArray();
        }

        $requiresApproval = $order->customer_type === 'guest';
        $approvalStatus = $order->approval_status ?? 'not_required';

        $canApprove = $requiresApproval && $approvalStatus === 'approval_pending';
        $canReject = $requiresApproval && $approvalStatus === 'approval_pending';

        $canPay = in_array($order->payment_status, ['unpaid', 'pending'])
            && ! in_array($order->status, ['completed', 'cancelled', 'expired'])
            && ($approvalStatus === 'not_required' || $approvalStatus === 'approved');

        $canSendToKitchen = match (true) {
            $requiresApproval && $approvalStatus !== 'approved' => false,
            $approvalStatus === 'rejected' => false,
            in_array($order->status, ['completed', 'cancelled', 'expired']) => false,
            default => true,
        };

        return [
            'id' => $order->id,
            'order_code' => $order->order_number,
            'table_id' => $order->table_id,
            'table_name' => $tableName ?? 'No table',
            'table_number' => $tableCode,
            'customer_id' => $order->customer_id,
            'customer_name' => $customerName,
            'customer_type' => $order->customer_type === 'member' ? 'logged_in' : 'guest',
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_timing' => $order->payment_timing,
            'approval_status' => $approvalStatus,
            'requires_approval' => $requiresApproval,
            'can_approve' => $canApprove,
            'can_reject' => $canReject,
            'can_pay' => $canPay,
            'can_send_to_kitchen' => $canSendToKitchen,
            'subtotal_amount' => (float) $order->subtotal_amount,
            'service_charge_amount' => (float) $order->service_charge_amount,
            'tax_amount' => (float) $order->tax_amount,
            'gross_total_amount' => (float) $order->gross_total_amount,
            'point_discount_amount' => (float) $order->point_discount_amount,
            'payable_amount' => (float) $order->payable_amount,
            'redeemed_points' => (int) $order->redeemed_points,
            'earned_points' => (int) $order->earned_points,
            'item_count' => $itemCount,
            'items_preview' => $itemsPreview,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'paid_at' => $order->paid_at ?? null,
        ];
    }

    public static function formatWithItems(Order $order): array
    {
        $formatted = self::format($order);

        if ($order->relationLoaded('items') && $order->items->isNotEmpty()) {
            $items = $order->items->map(fn ($i) => [
                'id' => $i->id,
                'menu_item_id' => $i->menu_item_id,
                'name' => $i->snapshot_name,
                'unit_price' => (float) $i->snapshot_price,
                'quantity' => (int) $i->quantity,
                'subtotal_amount' => (float) $i->subtotal_amount,
                'instruction' => $i->instruction,
            ])->values()->toArray();
        } else {
            $items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'menu_item_id' => $i->menu_item_id,
                    'name' => $i->snapshot_name,
                    'unit_price' => (float) $i->snapshot_price,
                    'quantity' => (int) $i->quantity,
                    'subtotal_amount' => (float) $i->subtotal_amount,
                    'instruction' => $i->instruction,
                ])->values()->toArray();
        }

        $formatted['items'] = $items;

        return $formatted;
    }
}
