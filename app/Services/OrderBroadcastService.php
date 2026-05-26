<?php

namespace App\Services;

use App\Events\OrderUpdated;
use App\Models\Order;

class OrderBroadcastService
{
    public function broadcastUpdate(Order $order, string $changeType): void
    {
        try {
            broadcast(new OrderUpdated($order, $changeType));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
