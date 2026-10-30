<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $table = 'company_subscription';

    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'status',
        'billing_cycle',
        'amount_paid',
        'currency',
        'payment_method',
        'starts_at',
        'expires_at',
        'trial_ends_at',
        'auto_renew',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function isActive(): bool
    {
        return ($this->status === 'active' || $this->status === 'trial') &&
               (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function getDaysLeftAttribute(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }
}
