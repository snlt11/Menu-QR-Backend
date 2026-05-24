<?php

namespace App\Http\Requests\Api\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'layout_type' => ['sometimes', 'string', 'in:horizontal_cards,grid_cards,large_featured_cards,compact_list,horizontal_scroll,large_featured,split_feature,mini_cards'],
            'display_order' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,expired'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
