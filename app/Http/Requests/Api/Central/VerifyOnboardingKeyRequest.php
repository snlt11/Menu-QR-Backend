<?php

namespace App\Http\Requests\Api\Central;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOnboardingKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'onboarding_key' => ['required', 'string'],
        ];
    }
}
