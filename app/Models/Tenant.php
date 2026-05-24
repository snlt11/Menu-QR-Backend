<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'database_name',
            'owner_name',
            'owner_email',
            'status',
        ];
    }

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'data' => 'array',
        ]);
    }
}
