<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'name', 'slug', 'description', 'layout_type', 'display_order', 'status', 'starts_at', 'ends_at'])]
class MenuCollection extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function collectionItems(): HasMany
    {
        return $this->hasMany(MenuCollectionItem::class, 'menu_collection_id');
    }
}
