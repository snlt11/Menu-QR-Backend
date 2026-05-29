<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'price', 'currency', 'billing_cycle', 'trial_days', 'max_owners', 'max_staff', 'max_kitchen', 'features', 'is_active', 'sort_order'])]
class Plan extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}
