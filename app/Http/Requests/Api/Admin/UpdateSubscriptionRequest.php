<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['nullable', 'string', 'exists:plans,id'],
            'status' => ['nullable', 'string', 'in:trialing,active,expired,cancelled'],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
