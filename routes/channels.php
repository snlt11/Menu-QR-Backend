<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenantSlug}.order.{orderId}', function ($user, $tenantSlug, $orderId) {
    $accessToken = request()->header('X-Order-Access-Token');

    if ($accessToken) {
        $order = Order::where('id', $orderId)
            ->where('public_access_token', $accessToken)
            ->first();

        if (! $order) {
            return false;
        }

        $tenant = tenant();
        if (! $tenant || $tenant->getTenantKey() !== $tenantSlug) {
            return false;
        }

        return [
            'id' => 'guest',
            'type' => 'guest',
            'order_id' => $orderId,
        ];
    }

    if (! $user) {
        return false;
    }

    $tenant = tenant();
    if (! $tenant || $tenant->getTenantKey() !== $tenantSlug) {
        return false;
    }

    $order = Order::where('id', $orderId)->first();
    if (! $order) {
        return false;
    }

    if (method_exists($user, 'getConnectionName') && $user->getConnectionName() === 'tenant') {
        return [
            'id' => $user->id,
            'type' => 'staff',
            'role' => $user->role ?? 'kitchen',
        ];
    }

    $isCustomer = $order->customer_id === $user->id;
    if ($isCustomer) {
        return [
            'id' => $user->id,
            'type' => 'customer',
            'order_id' => $orderId,
        ];
    }

    return false;
});
