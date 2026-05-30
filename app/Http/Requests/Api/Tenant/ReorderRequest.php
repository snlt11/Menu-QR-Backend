<?php

namespace App\Http\Requests\Api\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'string'],
            'order.*.sort_order' => ['nullable', 'integer'],
            'order.*.display_order' => ['nullable', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($validator->validated()['order'] ?? [] as $index => $item) {
                if (! isset($item['sort_order']) && ! isset($item['display_order'])) {
                    $validator->errors()->add(
                        "order.{$index}",
                        'Each item must have either sort_order or display_order.',
                    );
                }
            }
        });
    }
}
