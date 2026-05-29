<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTenantCanAcceptOrders;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\IdentifyTenantBySlug;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.slug' => IdentifyTenantBySlug::class,
            'admin' => EnsureAdmin::class,
            'tenant.role' => EnsureTenantRole::class,
            'tenant.sub' => EnsureTenantSubscriptionActive::class,
            'tenant.orders' => EnsureTenantCanAcceptOrders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
