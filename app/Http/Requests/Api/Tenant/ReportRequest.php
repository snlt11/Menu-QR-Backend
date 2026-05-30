<?php

namespace App\Http\Requests\Api\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'payment_status' => ['sometimes', 'string', 'in:paid,unpaid,pending,failed,expired'],
            'approval_status' => ['sometimes', 'string', 'in:not_required,approval_pending,approved,rejected'],
            'status' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
