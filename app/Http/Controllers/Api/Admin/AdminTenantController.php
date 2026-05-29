<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\CreateTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreTenantRequest;
use App\Http\Requests\Api\Admin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminTenantController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

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
            'onboarding_status' => $t->onboarding_status,
            'onboarded_at' => $t->onboarded_at,
            'owner_phone' => $t->data['owner_phone'] ?? null,
            'notes' => $t->data['notes'] ?? null,
            'created_at' => $t->created_at,
            'updated_at' => $t->updated_at,
        ];
    }

    public function index(): JsonResponse
    {
        $rows = Tenant::latest()->get()->map(fn (Tenant $t) => $this->tenantToArray($t));

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

    public function store(StoreTenantRequest $request, CreateTenantAction $action): JsonResponse
    {
        $data = $request->validated();

        try {
            $tenant = $action->execute($data['name'], $data['slug'], [
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => bcrypt(Str::random(32)),
            ]);

            if (! empty($data['owner_phone'])) {
                $tenant->data = array_merge($tenant->data ?? [], ['owner_phone' => $data['owner_phone']]);
                $tenant->save();
            }

            $plainKey = $this->onboardingService->generateKey($tenant);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not create tenant workspace. Please check the details and try again.',
            ], 500);
        }

        $result = $this->tenantToArray($tenant->refresh());
        $result['login_url'] = "/t/{$tenant->slug}/login";
        $result['onboarding_key'] = $plainKey;

        return response()->json(['status' => 201, 'data' => $result], 201);
    }

    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        $tenant = Tenant::find($id);
        if (! $tenant) {
            return response()->json(['status' => 404, 'message' => 'Tenant not found.'], 404);
        }

        $data = $request->validated();

        \Log::info('TENANT_UPDATE_RAW', [
            'id' => $id,
            'has_password_key' => array_key_exists('owner_password', $data),
            'password_value_set' => isset($data['owner_password']) && $data['owner_password'] !== null,
            'raw_input' => $request->input('owner_password'),
            'all_keys' => array_keys($request->all()),
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

            $ownerPassword = $data['owner_password'] ?? null;
            unset($data['owner_password']);

            $oldOwnerEmail = $tenant->owner_email;

            $tenant->fill($data);
            if (! empty($tenantData)) {
                $tenant->data = $tenantData;
            }
            $tenant->save();

            $this->syncOwnerToTenantDb($tenant, $oldOwnerEmail, $ownerPassword);

            \Log::info('TENANT_UPDATE_SYNC', [
                'old_email' => $oldOwnerEmail,
                'new_email' => $tenant->owner_email,
                'password_provided' => $ownerPassword ? 'yes' : 'no',
                'name_changed' => $tenant->wasChanged('owner_name') ? 'yes' : 'no',
                'email_changed' => $tenant->wasChanged('owner_email') ? 'yes' : 'no',
            ]);
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

    private function syncOwnerToTenantDb(Tenant $tenant, ?string $oldOwnerEmail, ?string $newPassword): void
    {
        $needsNameSync = $tenant->wasChanged('owner_name');
        $needsEmailSync = $tenant->wasChanged('owner_email');

        if (! $needsNameSync && ! $needsEmailSync && ! $newPassword) {
            return;
        }

        try {
            $tenant->run(function () use ($tenant, $oldOwnerEmail, $needsNameSync, $needsEmailSync, $newPassword) {
                $query = DB::table('users')->where('role', 'owner');

                if ($needsEmailSync && $oldOwnerEmail) {
                    $query->where('email', $oldOwnerEmail);
                }

                $updates = [];

                if ($needsNameSync) {
                    $updates['name'] = $tenant->owner_name;
                }

                if ($needsEmailSync) {
                    $updates['email'] = $tenant->owner_email;
                }

                if ($newPassword) {
                    $updates['password'] = Hash::make($newPassword);
                }

                if (! empty($updates)) {
                    $query->update($updates);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
