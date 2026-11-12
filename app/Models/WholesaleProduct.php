<?php

namespace App\Models;

use App\Traits\ResolvesStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WholesaleProduct extends Model
{
    use HasFactory, ResolvesStorageUrl;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'sku',
        'description',
        'wholesale_price',
        'retail_price',
        'currency',
        'moq',
        'stock',
        'image',
        'colors',
        'sizes',
        'is_active',
        'in_stock',
    ];

    protected $casts = [
        'wholesale_price' => 'float',
        'retail_price' => 'float',
        'moq' => 'integer',
        'stock' => 'integer',
        'colors' => 'array',
        'sizes' => 'array',
        'is_active' => 'boolean',
        'in_stock' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'category_label',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->image);
    }

    public function getCategoryLabelAttribute(): string
    {
        if ($this->relationLoaded('category') && $this->category) {
            return $this->category->name;
        }

        return $this->category()->value('name') ?: 'Wholesale';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WholesaleCategory::class, 'category_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WholesaleOrder::class, 'wholesale_product_id');
    }
}

