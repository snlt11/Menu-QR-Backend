<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantBySlug
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant_slug');

        if (! $slug) {
            return response()->json(['status' => 400, 'message' => 'Missing tenant slug.'], 400);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant || $tenant->status !== 'active') {
            return response()->json(['status' => 404, 'message' => 'Shop not found.'], 404);
        }

        tenancy()->initialize($tenant);

        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }
}
