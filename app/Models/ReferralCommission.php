<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    use HasFactory;

    protected $table = 'referral_commissions';

    protected $fillable = [
        'referrer_company_id',
        'referred_company_id',
        'company_subscription_id',
        'subscription_plan_id',
        'wallet_transaction_id',
        'subscription_amount',
        'commission_percentage',
        'commission_amount',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'subscription_amount' => 'decimal:2',
        'commission_percentage' => 'float',
        'commission_amount' => 'decimal:2',
    ];

    /**
     * The company that made the referral and received commission.
     */
    public function referrerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'referrer_company_id');
    }

    /**
     * The new company that was referred and purchased a subscription.
     */
    public function referredCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'referred_company_id');
    }

    /**
     * The subscription instance that triggered this commission.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CompanySubscription::class, 'company_subscription_id');
    }

    /**
     * The subscription plan purchased.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * The wallet transaction where commission was credited.
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
