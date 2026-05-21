<?php

namespace App\Providers;

use App\Http\Middleware\IdentifyTenantBySlug;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\DatabaseConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        DatabaseConfig::generateDatabaseNamesUsing(function (Tenant $tenant) {
            return $tenant->database_name
                ?? config('tenancy.database.prefix') . $tenant->slug . config('tenancy.database.suffix');
        });

        // Ensure tenant resolution runs before auth:sanctum so Sanctum can find
        // the TenantUser tokenable on the correct (tenant) connection.
        $this->app[Kernel::class]->prependToMiddlewarePriority(IdentifyTenantBySlug::class);
    }
}
