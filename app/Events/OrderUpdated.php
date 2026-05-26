<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $tenantSlug;

    public function __construct(
        public readonly Order $order,
        public readonly string $changeType,
    ) {
        $tenant = tenant();
        $this->tenantSlug = $tenant ? $tenant->getTenantKey() : '';

        Log::debug('OrderUpdated event constructed', [
            'channel' => "tenant.{$this->tenantSlug}.order.{$this->order->id}",
            'event' => $this->broadcastAs(),
            'change_type' => $changeType,
            'order_status' => $this->order->status,
            'approval_status' => $this->order->approval_status,
            'payment_status' => $this->order->payment_status,
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantSlug}.order.{$this->order->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'change_type' => $this->changeType,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'approval_status' => $this->order->approval_status,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }
}
