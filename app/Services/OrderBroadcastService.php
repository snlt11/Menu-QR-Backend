<?php

namespace App\Services;

use App\Events\OrderUpdated;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderBroadcastService
{
    public function broadcastUpdate(Order $order, string $changeType): void
    {
        try {
            Log::debug('OrderBroadcastService: dispatching', [
                'order_id' => $order->id,
                'change_type' => $changeType,
                'status' => $order->status,
                'tenant_initialized' => (bool) tenant(),
            ]);

            broadcast(new OrderUpdated($order, $changeType));

            Log::debug('OrderBroadcastService: broadcast dispatched successfully');
        } catch (\Throwable $e) {
            Log::error('OrderBroadcastService: broadcast failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
