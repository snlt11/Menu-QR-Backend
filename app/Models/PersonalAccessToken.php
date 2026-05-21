<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Tokens always live in the central DB so they survive tenant-context switches.
     */
    protected $connection = 'central';
}
