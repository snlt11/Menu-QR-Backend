<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderPricingService
{
    /**
     * @param  array<int, array{menu_item_id: string, quantity: int, instruction?: ?string}>  $items
     * @return array{lines: Collection<int, array<string, mixed>>, subtotal: float, service_charge: float, tax: float, gross_total: float}
     */
    public function calculate(array $items): array
    {
        $profile = DB::table('shop_profile')->first();
        $serviceRate = $profile ? (float) ($profile->service_charge_rate ?? 0) : 0.0;
        $taxRate = $profile ? (float) ($profile->tax_rate ?? 0) : 0.0;

        $ids = collect($items)->pluck('menu_item_id')->all();
        $menuItems = DB::table('menu_items')
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->where('is_available', true)
            ->get()
            ->keyBy('id');

        $lines = collect($items)->map(function ($line) use ($menuItems) {
            $mi = $menuItems->get($line['menu_item_id']);
            if (! $mi) {
                return null;
            }
            $qty = max(1, (int) $line['quantity']);
            $unit = (float) $mi->price;
            return [
                'menu_item_id' => $mi->id,
                'snapshot_name' => $mi->name,
                'snapshot_price' => $unit,
                'quantity' => $qty,
                'subtotal_amount' => round($unit * $qty, 2),
                'instruction' => $line['instruction'] ?? null,
            ];
        })->filter()->values();

        $subtotal = (float) $lines->sum('subtotal_amount');
        $serviceCharge = round($subtotal * $serviceRate / 100, 2);
        $tax = round($subtotal * $taxRate / 100, 2);
        $gross = round($subtotal + $serviceCharge + $tax, 2);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'service_charge' => $serviceCharge,
            'tax' => $tax,
            'gross_total' => $gross,
        ];
    }
}
