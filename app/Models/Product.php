<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'category_id',
        'title',
        'slug',
        'description',
        'stock',
        'regular_price',
        'sale_price',
        'has_variants',
        'is_available',
    ];

    protected $casts = [
        'has_variants' => 'boolean',
        'is_available' => 'boolean',
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    // All images for the product
    public function images()
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order', 'asc');
    }

    // Single primary image
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }
}
