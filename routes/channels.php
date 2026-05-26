<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenantSlug}.order.{orderId}', function ($user, $tenantSlug, $orderId) {
    try {
        $tenant = tenant();
        $resolvedSlug = $tenant ? ($tenant->slug ?? $tenant->getTenantKey()) : null;
    } catch (\Throwable $e) {
        Log::debug('Broadcast channel auth DENIED: tenant lookup failed', [
            'error' => $e->getMessage(),
        ]);

        return false;
    }

    Log::debug('Broadcast channel auth attempt', [
        'channel_tenant_slug' => $tenantSlug,
        'channel_order_id' => $orderId,
        'has_user' => (bool) $user,
        'user_class' => $user ? get_class($user) : null,
        'tenant_initialized' => (bool) $tenant,
        'resolved_slug' => $resolvedSlug,
    ]);

    $accessToken = request()->header('X-Order-Access-Token');

    if ($accessToken) {
        try {
            $order = Order::where('id', $orderId)
                ->where('public_access_token', $accessToken)
                ->first();
        } catch (\Throwable $e) {
            Log::debug('Broadcast channel auth DENIED: order lookup failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $order) {
            Log::debug('Broadcast channel auth DENIED: guest token mismatch', [
                'channel_order_id' => $orderId,
            ]);

            return false;
        }

        if (! $tenant || $resolvedSlug !== $tenantSlug) {
            Log::debug('Broadcast channel auth DENIED: tenant slug mismatch', [
                'expected' => $tenantSlug,
                'resolved' => $resolvedSlug,
            ]);

            return false;
        }

        Log::debug('Broadcast channel auth GRANTED: guest', [
            'order_id' => $orderId,
        ]);

        return [
            'id' => 'guest',
            'type' => 'guest',
            'order_id' => $orderId,
        ];
    }

    if (! $user) {
        Log::debug('Broadcast channel auth DENIED: no user, no token');

        return false;
    }

    if (! $tenant || $resolvedSlug !== $tenantSlug) {
        Log::debug('Broadcast channel auth DENIED: tenant slug mismatch (authed user)', [
            'expected' => $tenantSlug,
            'resolved' => $resolvedSlug,
        ]);

        return false;
    }

    try {
        $order = Order::where('id', $orderId)->first();
    } catch (\Throwable $e) {
        Log::debug('Broadcast channel auth DENIED: order lookup failed (authed)', [
            'error' => $e->getMessage(),
        ]);

        return false;
    }

    if (! $order) {
        Log::debug('Broadcast channel auth DENIED: order not found', [
            'order_id' => $orderId,
        ]);

        return false;
    }

    if (method_exists($user, 'getConnectionName') && $user->getConnectionName() === 'tenant') {
        Log::debug('Broadcast channel auth GRANTED: staff', [
            'user_id' => $user->id,
            'role' => $user->role ?? 'kitchen',
        ]);

        return [
            'id' => $user->id,
            'type' => 'staff',
            'role' => $user->role ?? 'kitchen',
        ];
    }

    $isCustomer = $order->customer_id === $user->id;
    if ($isCustomer) {
        Log::debug('Broadcast channel auth GRANTED: customer', [
            'user_id' => $user->id,
            'order_id' => $orderId,
        ]);

        return [
            'id' => $user->id,
            'type' => 'customer',
            'order_id' => $orderId,
        ];
    }

    Log::debug('Broadcast channel auth DENIED: no matching authorization');

    return false;
});
