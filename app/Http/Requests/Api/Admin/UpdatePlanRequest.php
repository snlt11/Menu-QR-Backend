<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:plans,slug,'.$planId],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'billing_cycle' => ['sometimes', 'required', 'string', 'in:trial,monthly,custom'],
            'trial_days' => ['nullable', 'integer', 'min:1'],
            'max_owners' => ['nullable', 'integer', 'min:1'],
            'max_staff' => ['nullable', 'integer', 'min:1'],
            'max_kitchen' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (array_key_exists('slug', $data) && empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
