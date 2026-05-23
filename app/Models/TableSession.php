<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableSession extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'table_id',
        'customer_id',
        'token',
        'status',
        'ip_address',
        'user_agent',
        'expires_at',
        'last_activity_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
