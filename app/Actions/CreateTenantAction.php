<?php

namespace App\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTenantAction
{
    /**
     * Provision a new shop (tenant) plus its owner row inside the tenant DB.
     *
     * Owner credentials live ONLY in the tenant DB. Central stores no shop users.
     *
     * @param  array{name: string, email: string, phone?: ?string, password: string}  $owner
     */
    public function execute(string $name, string $slug, array $owner): Tenant
    {
        $databaseName = config('tenancy.database.prefix') . $slug;

        return DB::transaction(function () use ($name, $slug, $databaseName, $owner) {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $slug,
                'database_name' => $databaseName,
                'owner_name' => $owner['name'],
                'owner_email' => $owner['email'],
                'status' => 'active',
            ]);

            $tenant->run(function () use ($name, $slug, $owner) {
                DB::table('profile')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'slug' => $slug,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('settings')->insert([
                    'id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('users')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => $owner['name'],
                    'email' => $owner['email'],
                    'phone' => $owner['phone'] ?? null,
                    'password' => Hash::make($owner['password']),
                    'role' => 'owner',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return $tenant;
        });
    }
}
