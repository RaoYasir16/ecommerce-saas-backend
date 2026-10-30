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
}