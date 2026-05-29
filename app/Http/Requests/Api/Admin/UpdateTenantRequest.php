<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug')->ignore($this->route('id'))],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive,suspended'],
            'owner_password' => ['nullable', 'string', 'min:6', 'max:255'],
            'owner_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
