<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'name', 'phone', 'email', 'total_points', 'membership_level'])]
class CustomerProfile extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'customer_profiles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
