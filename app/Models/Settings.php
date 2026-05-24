<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'payment_timing', 'allow_guest_order', 'allow_member_self_checkout', 'allow_cashier_checkout', 'allow_pay_after_meal', 'table_session_enabled', 'table_session_expiry_minutes', 'points_enabled', 'earn_rate_amount', 'earn_rate_points', 'redeem_rate_points', 'redeem_rate_amount'])]
class Settings extends Model
{
    protected $connection = 'tenant';

    protected $table = 'settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'allow_guest_order' => 'boolean',
            'allow_member_self_checkout' => 'boolean',
            'allow_cashier_checkout' => 'boolean',
            'allow_pay_after_meal' => 'boolean',
            'table_session_enabled' => 'boolean',
            'table_session_expiry_minutes' => 'integer',
            'points_enabled' => 'boolean',
            'earn_rate_amount' => 'integer',
            'earn_rate_points' => 'integer',
            'redeem_rate_points' => 'integer',
            'redeem_rate_amount' => 'integer',
        ];
    }
}
