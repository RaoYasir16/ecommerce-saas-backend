<?php

namespace App\Models;

use App\Traits\ResolvesStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WholesaleCategory extends Model
{
    use HasFactory, ResolvesStorageUrl;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->image);
    }

    public function products(): HasMany
    {
        return $this->hasMany(WholesaleProduct::class, 'category_id');
    }
}

