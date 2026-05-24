<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'menu_collection_id', 'menu_item_id', 'sort_order', 'is_featured'])]
class MenuCollectionItem extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'menu_collection_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MenuCollection::class, 'menu_collection_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
