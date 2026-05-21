<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->global_role !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Admin access required.',
            ], 403);
        }
        return $next($request);
    }
}
