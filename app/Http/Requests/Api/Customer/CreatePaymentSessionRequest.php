<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:qr_payment,cash'],
            'shown_on' => ['sometimes', 'nullable', 'string', 'in:customer_phone,cashier_screen'],
        ];
    }
}
