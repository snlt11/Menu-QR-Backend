<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantAction
{
    public function execute(User $owner, string $name, ?string $slug = null): Tenant
    {
        $slug ??= Str::slug($name);
        $databaseName = config('tenancy.database.prefix') . $slug;

        return DB::transaction(function () use ($owner, $name, $slug, $databaseName) {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $slug,
                'database_name' => $databaseName,
                'owner_user_id' => $owner->id,
                'status' => 'active',
            ]);

            $tenant->run(function () use ($owner, $name, $slug) {
                DB::table('shop_profile')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'slug' => $slug,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('shop_settings')->insert([
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('shop_users')->insert([
                    'id' => (string) Str::uuid(),
                    'central_user_id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'phone' => $owner->phone,
                    'role' => 'owner',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            DB::table('tenant_user_access')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role_in_tenant' => 'owner',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $tenant;
        });
    }
}
