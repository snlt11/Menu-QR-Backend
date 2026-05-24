<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Tenant\StoreTableRequest;
use App\Http\Requests\Api\Tenant\UpdateTableRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\TableAvailabilityHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Table::orderBy('table_number')->get();
        $enriched = TableAvailabilityHelper::enrichCollection($rows);
        $enriched = collect($enriched)->map(fn ($t) => $t + [
            'qr_url' => $this->buildQrUrl($t['public_code'] ?? $t['qr_token']),
        ])->all();

        return response()->json(['status' => 200, 'data' => $enriched]);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $data = $request->validated();

        $token = $this->generateUniqueToken();
        $publicCode = $this->generateUniquePublicCode();

        $table = Table::create([
            'table_number' => $data['table_number'],
            'table_name' => $data['table_name'] ?? null,
            'qr_token' => $token,
            'public_code' => $publicCode,
            'ordering_enabled' => true,
            'status' => $data['status'] ?? 'active',
        ]);

        $row = Table::where('id', $table->id)->first();

        return response()->json([
            'status' => 201,
            'data' => $row->toArray() + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ], 201);
    }

    public function show(string $tenant_slug, string $id): JsonResponse
    {
        $row = Table::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $row->toArray() + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    public function update(UpdateTableRequest $request, string $tenant_slug, string $id): JsonResponse
    {
        $row = Table::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $row->update($request->validated());
        $row = $row->fresh();

        return response()->json([
            'status' => 200,
            'data' => $row->toArray() + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    public function destroy(string $tenant_slug, string $id): JsonResponse
    {
        $row = Table::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $row->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function toggleOrdering(string $tenant_slug, string $id): JsonResponse
    {
        $row = Table::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $row->update(['ordering_enabled' => ! $row->ordering_enabled]);
        $row = $row->fresh();

        return response()->json([
            'status' => 200,
            'data' => $row->toArray() + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    public function blockSessions(string $tenant_slug, string $id): JsonResponse
    {
        $row = Table::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $sessions = TableSession::where('table_id', $id)
            ->where('status', 'active')
            ->get();

        $count = $sessions->count();

        TableSession::where('table_id', $id)
            ->where('status', 'active')
            ->each(fn ($s) => $s->update(['status' => 'blocked']));

        return response()->json([
            'status' => 200,
            'data' => ['blocked_count' => $count],
        ]);
    }

    public function resetQrCode(string $tenant_slug, string $id): JsonResponse
    {
        $row = Table::where('id', $id)->first();
        if (! $row) {
            return response()->json(['status' => 404, 'message' => 'Table not found.'], 404);
        }

        $row->update(['public_code' => $this->generateUniquePublicCode()]);
        $row = $row->fresh();

        return response()->json([
            'status' => 200,
            'data' => $row->toArray() + ['qr_url' => $this->buildQrUrl($row->public_code)],
        ]);
    }

    private function generateUniqueToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = 'tbl_'.Str::lower(Str::random(10));
            if (! Table::where('qr_token', $token)->exists()) {
                return $token;
            }
        }

        return 'tbl_'.Str::lower(Str::random(10)).Str::lower(Str::random(4));
    }

    private function generateUniquePublicCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = 'tbl_'.Str::lower(Str::random(10));
            if (! Table::where('public_code', $code)->exists()) {
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
