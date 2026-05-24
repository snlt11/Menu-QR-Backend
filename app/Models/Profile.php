<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'name', 'slug', 'phone', 'address', 'currency', 'service_charge_rate', 'tax_rate', 'opening_hours', 'status'])]
class Profile extends Model
{
    protected $connection = 'tenant';

    protected $table = 'profile';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'service_charge_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
        ];
    }
}
