<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class LoginCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required_without:email', 'string', 'max:30'],
            'email' => ['required_without:phone', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
