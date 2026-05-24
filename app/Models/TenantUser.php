<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class TenantUser extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    protected $connection = 'tenant';

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

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
