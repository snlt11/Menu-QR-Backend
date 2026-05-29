<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'plan_id', 'status', 'starts_at', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'cancelled_at', 'metadata'])]
class TenantSubscription extends Model
{
    use HasUuids;

    protected $connection = 'central';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class, 'subscription_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true);
    }

    public function getDaysLeft(): ?int
    {
        if ($this->status === 'trialing' && $this->trial_ends_at) {
            $diff = now()->diffInDays($this->trial_ends_at, false);

            return max(0, (int) $diff);
        }

        if ($this->status === 'active' && $this->current_period_ends_at) {
            $diff = now()->diffInDays($this->current_period_ends_at, false);

            return max(0, (int) $diff);
        }

        return null;
    }
}
