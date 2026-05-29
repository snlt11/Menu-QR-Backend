<?php

namespace App\Http\Middleware;

use App\Services\TenantSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function __construct(
        private readonly TenantSubscriptionService $subscriptionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenancy()->initialized ? tenancy()->tenant : null;

        if (! $tenant) {
            return $next($request);
        }

        if (! $this->subscriptionService->isUsable($tenant)) {
            return response()->json([
                'status' => 403,
                'message' => 'Your trial or subscription has expired. Please contact admin to continue using Menu QR.',
            ], 403);
        }

        return $next($request);
    }
}
