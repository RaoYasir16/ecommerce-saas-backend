<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Order extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'payment_status',
                'payment_method',
                'customer_name',
                'customer_email',
                'customer_phone',
                'shipping_address',
                'city',
                'total_amount',
                'cancel_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('order')
            ->setDescriptionForEvent(fn(string $eventName) => "Order #{$this->order_number} was {$eventName}");
    }

    protected $fillable = [
        'company_id',
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'city',
        'postal_code',
        'notes',
        'subtotal',
        'shipping_fee',
        'tax',
        'discount',
        'total_amount',
        'cancel_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
