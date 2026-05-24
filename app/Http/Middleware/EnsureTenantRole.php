<?php

namespace App\Http\Middleware;

use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof TenantUser) {
            return response()->json([
                'status' => 403,
                'message' => 'You do not have permission to access this page.',
            ], 403);
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return response()->json([
            'status' => 403,
            'message' => 'You do not have permission to access this page.',
        ], 403);
    }
}
