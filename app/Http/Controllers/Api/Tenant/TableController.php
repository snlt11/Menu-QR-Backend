<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\TableAvailabilityHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('tables')->orderBy('table_number')->get();
        $enriched = TableAvailabilityHelper::enrichCollection($rows);
        $enriched = collect($enriched)->map(fn ($t) => $t + [
            'qr_url' => $this->buildQrUrl($t['public_code'] ?? $t['qr_token']),
        ])->all();

        return response()->json(['status' => 200, 'data' => $enriched]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_number' => ['required', 'string', 'max:32'],
            'table_name' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $token = $this->generateUniqueToken();
        $publicCode = $this->generateUniquePublicCode();

        $id = (string) Str::uuid();
        DB::table('tables')->insert([
            'id' => $id,
            'table_number' => $data['table_number'],
            'table_name' => $data['table_name'] ?? null,
            'qr_token' => $token,
            'public_code' => $publicCode,
            'ordering_enabled' => true,
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('tables')->where('id', $id)->first();

        return response()->json([
            'status' => 201,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('tables')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    public function update(Request $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('tables')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $data = $request->validate([
            'table_number' => ['sometimes', 'string', 'max:32'],
            'table_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'ordering_enabled' => ['sometimes', 'boolean'],
        ]);

        DB::table('tables')->where('id', $id)->update($data + ['updated_at' => now()]);

        $row = DB::table('tables')->where('id', $id)->first();

        return response()->json([
            'status' => 200,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('tables')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        DB::table('tables')->where('id', $id)->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function toggleOrdering(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('tables')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $newValue = ! ((bool) $row->ordering_enabled);
        DB::table('tables')->where('id', $id)->update([
            'ordering_enabled' => $newValue,
            'updated_at' => now(),
        ]);

        $row = DB::table('tables')->where('id', $id)->first();

        return response()->json([
            'status' => 200,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    public function blockSessions(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('tables')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $count = DB::table('table_sessions')
            ->where('table_id', $id)
            ->where('status', 'active')
            ->update(['status' => 'blocked', 'updated_at' => now()]);

        return response()->json([
            'status' => 200,
            'data' => ['blocked_count' => $count],
        ]);
    }

    public function resetQrCode(string $tenant_slug, string $id): JsonResponse
    {
        $row = DB::table('tables')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $newCode = $this->generateUniquePublicCode();
        DB::table('tables')->where('id', $id)->update([
            'public_code' => $newCode,
            'updated_at' => now(),
        ]);

        $row = DB::table('tables')->where('id', $id)->first();

        return response()->json([
            'status' => 200,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    private function generateUniqueToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = 'tbl_'.Str::lower(Str::random(10));
            if (! DB::table('tables')->where('qr_token', $token)->exists()) {
                return $token;
            }
        }

        return 'tbl_'.Str::lower(Str::random(10)).Str::lower(Str::random(4));
    }

    private function generateUniquePublicCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = 'tbl_'.Str::lower(Str::random(10));
            if (! DB::table('tables')->where('public_code', $code)->exists()) {
                return $code;
            }
        }

        return 'tbl_'.Str::lower(Str::random(10)).Str::lower(Str::random(4));
    }

    private function buildQrUrl(string $code): string
    {
        $base = config('app.frontend_url') ?: config('app.url');

        return rtrim($base, '/').'/s/'.tenant('slug').'/table/'.$code;
    }
}
