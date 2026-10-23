<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'currency',
        'trial_days',
        'product_limit',
        'order_limit',
        'features',
        'is_active',
        'is_popular',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_days' => 'integer',
        'product_limit' => 'integer',
        'order_limit' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
    ];
}
