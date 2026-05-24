<?php

namespace App\Http\Requests\Api\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points_enabled' => ['sometimes', 'boolean'],
            'earn_rate_amount' => ['sometimes', 'integer', 'min:1'],
            'earn_rate_points' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_points' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_amount' => ['sometimes', 'integer', 'min:1'],
            'table_session_enabled' => ['sometimes', 'boolean'],
            'table_session_expiry_minutes' => ['sometimes', 'integer', 'min:5', 'max:1440'],
        ];
    }
}
