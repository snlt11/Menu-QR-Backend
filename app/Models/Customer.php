<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $connection = 'tenant';

    protected $table = 'customers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function newQuery()
    {
        if (! tenancy()->initialized) {
            $this->setConnection(config('tenancy.database.central_connection'));

            return parent::newQuery()->whereRaw('1=0');
        }

        return parent::newQuery();
    }
}
