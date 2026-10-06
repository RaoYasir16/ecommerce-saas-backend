<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'description',
        'stock',
        'regular_price',
        'sale_price',
        'is_available',
    ];
    protected $casts = [
        'is_available' => 'boolean',
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];
    
    public function tenant()
    {
        return $this->belongsTo(Company::class);
    }

    // All images for the product
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order', 'asc');
    }

    // Single primary image helper (for product cards on storefront)
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
