<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('tables')->orderBy('table_number')->get()
            ->map(fn ($t) => (array) $t + ['qr_url' => $this->buildQrUrl($t->qr_token)]);

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_number' => ['required', 'string', 'max:32'],
            'table_name' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        $token = $this->generateUniqueToken();

        $id = (string) Str::uuid();
        DB::table('tables')->insert([
            'id' => $id,
            'table_number' => $data['table_number'],
            'table_name' => $data['table_name'] ?? null,
            'qr_token' => $token,
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('tables')->where('id', $id)->first();

        return response()->json([
            'status' => 201,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->qr_token)],
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
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->qr_token)],
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
        ]);

        DB::table('tables')->where('id', $id)->update($data + ['updated_at' => now()]);

        $row = DB::table('tables')->where('id', $id)->first();

        return response()->json([
            'status' => 200,
            'data' => (array) $row + ['qr_url' => $this->buildQrUrl($row->qr_token)],
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

    private function buildQrUrl(string $token): string
    {
        $base = config('app.frontend_url') ?: config('app.url');
        return rtrim($base, '/').'/s/'.tenant('slug').'/table/'.$token;
    }
}
