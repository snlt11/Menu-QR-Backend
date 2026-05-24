<?php

namespace App\Http\Requests\Api\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AttachCollectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_item_id' => ['required', 'string', 'exists:menu_items,id'],
            'sort_order' => ['sometimes', 'integer'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}
