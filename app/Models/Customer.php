<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'status'])]
#[Hidden(['password'])]
class Customer extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $connection = 'tenant';

    protected $table = 'customers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'customer_id');
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
