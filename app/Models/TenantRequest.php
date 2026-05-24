<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_name',
        'requested_slug',
        'owner_name',
        'owner_email',
        'owner_phone',
        'status',
        'tenant_id',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'data',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'data' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
