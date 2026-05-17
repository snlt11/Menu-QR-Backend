<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantShopUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['status' => 401, 'message' => 'Unauthenticated.'], 401);
        }

        $shopUser = DB::table('users')
            ->where('central_user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $shopUser) {
            return response()->json(['status' => 403, 'message' => 'No shop access.'], 403);
        }

        $request->attributes->set('shop_user', $shopUser);

        return $next($request);
    }
}
