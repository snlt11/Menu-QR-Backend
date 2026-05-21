<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tenant-scoped user (owner, manager, cashier, kitchen).
 * Lives in the tenant database under the `users` table — never in central.
 */
class TenantUser extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $connection = 'tenant';

    /**
     * When Sanctum resolves a token whose tokenable is a TenantUser, the
     * lookup may happen outside any tenant context (e.g. the bearer was
     * sent to an admin route). In that case stancl hasn't bound the
     * `tenant` connection to a database — re-point the model at the
     * central connection with a no-match clause so Sanctum sees "no
     * such user" and the request 401s gracefully.
     */
    public function newQuery()
    {
        if (! tenancy()->initialized) {
            $this->setConnection(config('tenancy.database.central_connection'));
            return parent::newQuery()->whereRaw('1=0');
        }
        return parent::newQuery();
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}

