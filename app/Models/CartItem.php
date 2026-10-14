<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'product_id',
        'product_variant_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $appends = [
        'unit_price',
        'subtotal',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get current unit price for this cart item.
     */
    public function getUnitPriceAttribute(): float
    {
        if ($this->variant) {
            return (float) ($this->variant->sale_price ?? $this->variant->regular_price ?? 0);
        }

        if ($this->product) {
            return (float) ($this->product->sale_price ?? $this->product->regular_price ?? 0);
        }

        return 0.0;
    }

    /**
     * Get subtotal for this item.
     */
    public function getSubtotalAttribute(): float
    {
        return round($this->unit_price * $this->quantity, 2);
    }
}
