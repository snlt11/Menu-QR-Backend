<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\CreateTenantAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminTenantController extends Controller
{
    private function tenantToArray(Tenant $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'database_name' => $t->database_name,
            'owner_name' => $t->owner_name,
            'owner_email' => $t->owner_email,
            'status' => $t->status,
            'owner_phone' => $t->data['owner_phone'] ?? null,
            'notes' => $t->data['notes'] ?? null,
            'created_at' => $t->created_at,
            'updated_at' => $t->updated_at,
        ];
    }

    public function index(): JsonResponse
    {
        $rows = Tenant::orderBy('created_at', 'desc')->get()->map(fn (Tenant $t) => $this->tenantToArray($t));

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        return response()->json(['status' => 200, 'data' => $this->tenantToArray($tenant)]);
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

        try {
            $tenant = $action->execute($data['name'], $data['slug'], [
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => $data['password'],
            ]);

            if (! empty($data['owner_phone'])) {
                $tenant->data = array_merge($tenant->data ?? [], ['owner_phone' => $data['owner_phone']]);
                $tenant->save();
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not create tenant workspace. Please check the details and try again.',
            ], 500);
        }

        $result = $this->tenantToArray($tenant);
        $result['login_url'] = "/t/{$tenant->slug}/login";

        return response()->json(['status' => 201, 'data' => $result], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive,suspended'],
            'owner_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            $tenantData = $tenant->data ?? [];
            if (array_key_exists('owner_phone', $data)) {
                $tenantData['owner_phone'] = $data['owner_phone'];
                unset($data['owner_phone']);
            }
            if (array_key_exists('notes', $data)) {
                $tenantData['notes'] = $data['notes'];
                unset($data['notes']);
            }

            $tenant->fill($data);
            if (! empty($tenantData)) {
                $tenant->data = $tenantData;
            }
            $tenant->save();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not update tenant. Please try again.',
            ], 500);
        }

        return response()->json(['status' => 200, 'data' => $this->tenantToArray($tenant)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        $databaseName = $tenant->database_name;

        try {
            $tenant->delete();
        } catch (\Throwable $e) {
            if ($databaseName && preg_match('/^[A-Za-z0-9_]+$/', $databaseName) && str_starts_with($databaseName, 'tenant_')) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
                } catch (\Throwable $inner) {
                    report($inner);
                }
            }

            if (Tenant::find($id)) {
                report($e);

                return response()->json([
                    'message' => 'Could not delete tenant. Please try again.',
                ], 500);
            }
        }

        return response()->json(['message' => 'Tenant deleted successfully.']);
    }
}
