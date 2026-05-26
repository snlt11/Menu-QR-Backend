<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BroadcastAuthController extends Controller
{
    public function auth(Request $request, string $tenant_slug): JsonResponse
    {
        $channelName = $request->input('channel_name', '');
        $socketId = $request->input('socket_id', '');

        Log::debug('BroadcastAuth: request', [
            'channel' => $channelName,
            'has_socket_id' => ! empty($socketId),
            'has_token' => (bool) $request->header('X-Order-Access-Token'),
            'tenant_slug' => $tenant_slug,
        ]);

        if (empty($channelName) || empty($socketId)) {
            return response()->json(['message' => 'Missing channel_name or socket_id'], 400);
        }

        if (! str_starts_with($channelName, 'private-')) {
            return response()->json(['message' => 'Only private channels supported'], 403);
        }

        $normalized = substr($channelName, 8);

        if (! preg_match('/^tenant\.(?P<tenantSlug>[^.]+)\.order\.(?P<orderId>.+)$/', $normalized, $parsed)) {
            return response()->json(['message' => 'Invalid channel format'], 403);
        }

        if ($parsed['tenantSlug'] !== $tenant_slug) {
            Log::debug('BroadcastAuth: slug mismatch', [
                'route' => $tenant_slug,
                'channel' => $parsed['tenantSlug'],
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $orderId = $parsed['orderId'];
        $accessToken = $request->header('X-Order-Access-Token');

        if (! $accessToken) {
            Log::debug('BroadcastAuth: no access token');

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $order = Order::where('id', $orderId)
                ->where('public_access_token', $accessToken)
                ->first();
        } catch (\Throwable $e) {
            Log::error('BroadcastAuth: order lookup failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $order) {
            Log::debug('BroadcastAuth: token mismatch', ['order_id' => $orderId]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appKey = config('broadcasting.connections.reverb.key');
        $appSecret = config('broadcasting.connections.reverb.secret');

        $signature = hash_hmac('sha256', $socketId . ':' . $channelName, $appSecret, false);

        Log::debug('BroadcastAuth: granted', ['order_id' => $orderId]);

        return response()->json([
            'auth' => $appKey . ':' . $signature,
        ]);
    }
}
