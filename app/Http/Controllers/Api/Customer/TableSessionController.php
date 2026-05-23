<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class TableSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_code' => ['required', 'string'],
        ]);

        $code = $data['public_code'];

        $table = DB::table('tables')
            ->where(function ($q) use ($code) {
                $q->where('public_code', $code)
                    ->orWhere('qr_token', $code);
            })
            ->where('status', 'active')
            ->first();

        if (! $table) {
            return response()->json([
                'status' => 404,
                'error' => 'invalid_qr',
                'message' => 'Invalid table QR.',
            ], 404);
        }

        if (! $table->ordering_enabled) {
            return response()->json([
                'status' => 403,
                'error' => 'ordering_disabled',
                'message' => 'Ordering is currently disabled for this table.',
            ], 403);
        }

        $settings = DB::table('settings')->first();

        $sessionEnabled = $settings ? (bool) $settings->table_session_enabled : true;

        $customerId = $this->resolveCustomerId($request);

        if (! $sessionEnabled) {
            return response()->json([
                'status' => 200,
                'data' => [
                    'table' => [
                        'name' => $table->table_name ?? "Table {$table->table_number}",
                        'public_code' => $table->public_code,
                        'id' => $table->id,
                        'table_number' => $table->table_number,
                    ],
                    'table_session_token' => null,
                    'session_enabled' => false,
                ],
            ]);
        }

        $expiryMinutes = $settings ? (int) $settings->table_session_expiry_minutes : 120;

        $token = 'sess_'.Str::random(32);

        $sessionId = (string) Str::uuid();
        TableSession::create([
            'id' => $sessionId,
            'table_id' => $table->id,
            'customer_id' => $customerId,
            'token' => $token,
            'status' => 'active',
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            'expires_at' => now()->addMinutes($expiryMinutes),
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'table' => [
                    'name' => $table->table_name ?? "Table {$table->table_number}",
                    'public_code' => $table->public_code,
                    'id' => $table->id,
                    'table_number' => $table->table_number,
                ],
                'table_session_token' => $token,
                'session_enabled' => true,
            ],
        ]);
    }

    private function resolveCustomerId(Request $request): ?string
    {
        $user = $request->user();
        if ($user instanceof Customer) {
            return $user->id;
        }

        $header = $request->bearerToken();
        if (! $header) {
            return null;
        }

        $token = PersonalAccessToken::findToken($header);
        if (! $token) {
            return null;
        }

        $owner = $token->tokenable;
        if ($owner instanceof Customer && $token->can('customer')) {
            return $owner->id;
        }

        return null;
    }
}
