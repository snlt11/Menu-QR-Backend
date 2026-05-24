<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'order_number',
    'table_id',
    'table_session_id',
    'customer_id',
    'customer_type',
    'checkout_type',
    'payment_timing',
    'status',
    'payment_status',
    'approval_status',
    'subtotal_amount',
    'service_charge_amount',
    'tax_amount',
    'gross_total_amount',
    'redeemed_points',
    'point_discount_amount',
    'payable_amount',
    'earned_points',
    'paid_at',
])]
class Order extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'gross_total_amount' => 'decimal:2',
            'point_discount_amount' => 'decimal:2',
            'payable_amount' => 'decimal:2',
            'redeemed_points' => 'integer',
            'earned_points' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeNeedsAttention($query): void
    {
        $query->where(function ($q) {
            $q->where('approval_status', 'approval_pending')
                ->orWhereIn('payment_status', ['failed', 'expired'])
                ->orWhere(function ($sq) {
                    $sq->where('payment_status', 'unpaid')
                        ->where('created_at', '<', now()->subMinutes(30));
                });
        });
    }

    public function scopeUnpaid($query): void
    {
        $query->whereIn('payment_status', ['unpaid', 'pending']);
    }

    public function getIsFinalAttribute(): bool
    {
        return in_array($this->status, ['completed', 'cancelled', 'expired']);
    }

    public function getIsKitchenActiveAttribute(): bool
    {
        $kitchenStatuses = ['submitted', 'accepted', 'preparing'];

        return ! $this->is_final
            && $this->approval_status !== 'approval_pending'
            && (
                ($this->payment_timing === 'pay_after_meal' && in_array($this->status, $kitchenStatuses))
                || ($this->payment_timing === 'pay_before_prepare' && $this->payment_status === 'paid' && in_array($this->status, $kitchenStatuses))
            );
    }
}
