<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class TableAvailabilityHelper
{
    public static function getActiveOrdersForTables(array $tableIds): Collection
    {
        if (empty($tableIds)) {
            return collect();
        }

        return Order::select(['id', 'order_number', 'table_id'])
            ->whereIn('table_id', $tableIds)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(function ($q) {
                $q->where('status', '!=', 'completed')
                    ->orWhereNull('paid_at')
                    ->orWhere('payment_status', '!=', 'paid');
            })
            ->get()
            ->unique('table_id')
            ->keyBy('table_id');
    }

    public static function enrichTable(object $table, ?Order $activeOrder = null): array
    {
        $isOccupied = $activeOrder !== null;

        return [
            'id' => $table->id,
            'table_number' => $table->table_number,
            'table_name' => $table->table_name ?? null,
            'qr_token' => $table->qr_token ?? null,
            'status' => $table->status ?? 'active',
            'is_available' => ! $isOccupied,
            'is_occupied' => $isOccupied,
            'active_order_id' => $activeOrder?->id,
            'active_order_code' => $activeOrder?->order_number,
        ];
    }

    public static function enrichCollection(Collection $tables): array
    {
        $tableIds = $tables->pluck('id')->toArray();
        $activeOrders = self::getActiveOrdersForTables($tableIds);

        return $tables->map(fn ($table) => self::enrichTable($table, $activeOrders->get($table->id)))->values()->all();
    }
}
