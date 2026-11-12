<?php

namespace App\Models;

use App\Traits\ResolvesStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleOrder extends Model
{
    use HasFactory, ResolvesStorageUrl;

    protected $fillable = [
        'order_number',
        'company_id',
        'user_id',
        'wholesale_product_id',
        'product_title',
        'product_sku',
        'product_image',
        'selected_color',
        'selected_size',
        'quantity',
        'unit_price',
        'total_amount',
        'currency',
        'payment_method',
        'payment_status',
        'status',
        'shipping_address',
        'whatsapp_phone',
        'notes',
        'admin_notes',
        'cancelled_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'total_amount' => 'float',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'product_image_url',
    ];

    public function getProductImageUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->product_image);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'wholesale_product_id');
    }
}

