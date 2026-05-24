<?php

namespace App\Http\Controllers\Api\Customer;

use App\Actions\ResolveCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\StoreTableSessionRequest;
use App\Models\Settings;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TableSessionController extends Controller
{
    public function __construct(
        private readonly ResolveCustomerAction $resolveCustomer,
    ) {}

    public function store(StoreTableSessionRequest $request): JsonResponse
    {
        $code = $request->validated('public_code');

        $table = Table::where(function ($q) use ($code) {
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

        $settings = Settings::first();
        $sessionEnabled = $settings ? (bool) $settings->table_session_enabled : true;

        $customerId = $this->resolveCustomer->resolveId($request);

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

        TableSession::create([
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
}
