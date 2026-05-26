<?php

namespace App\Models;

use App\Services\PrivateS3Service;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'menu_category_id', 'name', 'slug', 'description', 'price', 'currency', 'image_path', 'image_url', 'is_available', 'status'])]
class MenuItem extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $value = $this->attributes['image_url'] ?? $this->attributes['image_path'] ?? null;

        if (! $value) {
            return null;
        }

        return app(PrivateS3Service::class)->resolveImageUrl($value);
    }
}
