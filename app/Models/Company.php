<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'domain',
        'domain_status',
        'logo',
        'whatsapp_number',
        'email',
        'address',
        'trial_ends_at',
        'status',
        'referral_code',
        'referred_by',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'domain_status' => 'string',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get complete logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return Storage::disk('public')->url($this->logo);
    }

    public function storeSetting(): HasOne
    { 
        return $this->hasOne(StoreSetting::class); 
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function visits()
    {
        return $this->hasMany(StoreVisit::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CompanyWallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function transactionHistory()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function depositRequests()
    {
        return $this->hasMany(WalletDepositRequest::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class)->latestOfMany();
    }

    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function referrer()
    {
        return $this->belongsTo(Company::class, 'referred_by');
    }

    public function referredCompanies()
    {
        return $this->hasMany(Company::class, 'referred_by');
    }

    public function referralCommissions()
    {
        return $this->hasMany(ReferralCommission::class, 'referrer_company_id');
    }

    public function commissionsGenerated()
    {
        return $this->hasMany(ReferralCommission::class, 'referred_company_id');
    }

    /**
     * Get or generate unique referral code.
     */
    public function getOrCreateReferralCode(): string
    {
        if (!empty($this->referral_code)) {
            return $this->referral_code;
        }

        $code = 'REF-' . strtoupper(\Illuminate\Support\Str::random(6));
        while (static::where('referral_code', $code)->exists()) {
            $code = 'REF-' . strtoupper(\Illuminate\Support\Str::random(6));
        }

        $this->update(['referral_code' => $code]);
        return $code;
    }

    /**
     * Get company's active paid subscription (if not expired).
     */
    public function getActiveSubscription(): ?CompanySubscription
    {
        $subscription = CompanySubscription::with('plan')
            ->where('company_id', $this->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($subscription && $subscription->expires_at && $subscription->expires_at->isPast()) {
            $subscription->update(['status' => 'expired']);
            return null;
        }

        return $subscription;
    }

    /**
     * Get company's active subscription plan.
     * Returns the active paid plan or active trial plan (Starter). Returns null if expired or no active plan.
     */
    public function getActivePlan(): ?SubscriptionPlan
    {
        // 1. Check paid active subscription
        $activeSub = $this->getActiveSubscription();
        if ($activeSub && $activeSub->plan && $activeSub->plan->is_active) {
            return $activeSub->plan;
        }

        // 2. Check active trial period or unpaid company
        // A company in trial (status === 'trial', or trial_ends_at is future/null, or no paid subscription yet)
        // is granted the lowest/most basic active package so they can add products and accept orders.
        $isTrial = ($this->status === 'trial') ||
                   (!$this->trial_ends_at || $this->trial_ends_at->isFuture()) ||
                   (!$activeSub && $this->status !== 'suspended' && $this->status !== 'expired');

        if ($isTrial) {
            // Find the most basic plan (plan with lowest product_limit > 0 or lowest price)
            $basicPlan = SubscriptionPlan::where('is_active', true)
                ->whereNotNull('product_limit')
                ->where('product_limit', '>', 0)
                ->orderBy('product_limit', 'asc')
                ->orderBy('price', 'asc')
                ->first();

            if (!$basicPlan) {
                $basicPlan = SubscriptionPlan::where('slug', 'starter')->first()
                    ?? SubscriptionPlan::where('is_active', true)->orderBy('price', 'asc')->first();
            }

            if ($basicPlan) {
                return $basicPlan;
            }

            // Fallback virtual plan if database has no seeded plans
            return new SubscriptionPlan([
                'name' => 'Starter Trial',
                'slug' => 'starter',
                'product_limit' => 50,
                'order_limit' => 200,
                'price' => 0,
                'is_active' => true,
            ]);
        }

        return null;
    }

    /**
     * Check if company can add a product according to its active plan limit.
     */
    public function canAddProduct(): array
    {
        $plan = $this->getActivePlan();

        if (!$plan) {
            return [
                'allowed' => false,
                'message' => 'No active subscription plan found. Please purchase or upgrade your plan to add products.',
                'code' => 'NO_ACTIVE_PLAN',
            ];
        }

        if ($plan->product_limit !== null && $plan->product_limit >= 0) {
            $currentCount = $this->products()->count();
            if ($currentCount >= $plan->product_limit) {
                return [
                    'allowed' => false,
                    'message' => "Product limit reached for your plan (limit: {$plan->product_limit} products). Please upgrade your subscription plan to add more products.",
                    'code' => 'PRODUCT_LIMIT_REACHED',
                    'current' => $currentCount,
                    'limit' => $plan->product_limit,
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => 'Product can be added.',
            'code' => 'OK',
            'plan' => $plan,
        ];
    }

    /**
     * Check if company can accept a new customer order according to its active plan limit.
     */
    public function canAcceptOrder(): array
    {
        $plan = $this->getActivePlan();

        if (!$plan) {
            return [
                'allowed' => false,
                'message' => 'Store does not have an active subscription plan to accept orders.',
                'code' => 'NO_ACTIVE_PLAN',
            ];
        }

        if ($plan->order_limit !== null && $plan->order_limit >= 0) {
            $currentOrders = $this->orders()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            if ($currentOrders >= $plan->order_limit) {
                return [
                    'allowed' => false,
                    'message' => "Monthly order limit reached for your plan (limit: {$plan->order_limit} orders). Please upgrade your subscription plan to accept more orders.",
                    'code' => 'ORDER_LIMIT_COMPLETE',
                    'current' => $currentOrders,
                    'limit' => $plan->order_limit,
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => 'Order can be placed.',
            'code' => 'OK',
            'plan' => $plan,
        ];
    }
}