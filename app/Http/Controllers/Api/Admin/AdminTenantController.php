<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\CreateTenantAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTenantController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Tenant::orderBy('created_at', 'desc')->get()->map(fn (Tenant $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'database_name' => $t->database_name,
            'owner_name' => $t->owner_name,
            'owner_email' => $t->owner_email,
            'status' => $t->status,
            'created_at' => $t->created_at,
            'updated_at' => $t->updated_at,
        ]);

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }
        return response()->json([
            'status' => 200,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database_name' => $tenant->database_name,
                'owner_name' => $tenant->owner_name,
                'owner_email' => $tenant->owner_email,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ],
        ]);
    }

    public function store(Request $request, CreateTenantAction $action): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug')],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $tenant = $action->execute($data['name'], $data['slug'], [
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'phone' => $data['owner_phone'] ?? null,
            'password' => $data['password'],
        ]);

        return response()->json([
            'status' => 201,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'database_name' => $tenant->database_name,
                'owner_name' => $tenant->owner_name,
                'owner_email' => $tenant->owner_email,
                'status' => $tenant->status,
                'login_url' => "/t/{$tenant->slug}/login",
            ],
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
        ]);

        $tenant->fill($data)->save();

        return $this->show($id);
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        // Best-effort: drop the tenant database only if it still exists.
        try {
            $manager = $tenant->database()->manager();
            if ($manager->databaseExists($tenant->database()->getName())) {
                $manager->deleteDatabase($tenant);
            }
        } catch (\Throwable $e) {
            // ignore — the row deletion below is still useful
        }
        $tenant->delete();

        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }
}
