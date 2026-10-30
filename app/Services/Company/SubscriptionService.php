<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSetting;
use App\Models\ReferralCommission;
use App\Models\SubscriptionPlan;
use App\Models\WalletTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubscriptionService
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Get all active subscription plans for merchant selection.
     */
    public function getPlans()
    {
        return SubscriptionPlan::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get();
    }

    /**
     * Get current subscription for a company.
     * When on trial: no plan is marked active, but Starter plan quota limits apply.
     * When paid plan purchased: that plan is active and its quotas apply.
     */
    public function getCurrentSubscription(Company $company): array
    {
        // Find latest active paid subscription
        $subscription = CompanySubscription::with('plan')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        // Check if subscription has expired
        if ($subscription && $subscription->expires_at && $subscription->expires_at->isPast()) {
            $subscription->update(['status' => 'expired']);
            $subscription = null;
        }

        $hasActiveSubscription = !is_null($subscription);
        $isTrial = !$hasActiveSubscription && $company->status === 'trial' && ($company->trial_ends_at ? $company->trial_ends_at->isFuture() : true);
        $isExpired = !$hasActiveSubscription && !$isTrial;

        // Starter plan used for trial quota limits
        $starterPlan = SubscriptionPlan::where('slug', 'starter')->first()
            ?? SubscriptionPlan::orderBy('price', 'asc')->first();

        $activePlan = $subscription ? $subscription->plan : null;
        $effectivePlanForLimits = $activePlan ?? $starterPlan;

        // Calculate usage quotas
        $productsCount = $company->products()->count();
        $ordersCount = $company->orders()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $productLimit = $effectivePlanForLimits?->product_limit;
        $orderLimit = $effectivePlanForLimits?->order_limit;

        $trialDaysLeft = $company->trial_ends_at
            ? max(0, (int) now()->diffInDays($company->trial_ends_at, false))
            : 0;

        $subscriptionDaysLeft = $subscription?->days_left ?? 0;

        // Current wallet balance
        $wallet = $this->walletService->resolveWallet($company);

        // Check if merchant ever had any subscription
        $latestSubscription = CompanySubscription::with('plan')
            ->where('company_id', $company->id)
            ->latest('id')
            ->first();

        return [
            'has_active_subscription' => $hasActiveSubscription,
            'subscription' => $subscription,
            'latest_subscription' => $latestSubscription,
            'had_previous_subscription' => !is_null($latestSubscription),
            'plan' => $activePlan, // null during trial!
            'is_trial' => $isTrial,
            'is_expired' => $isExpired,
            'status' => $hasActiveSubscription ? 'active' : ($isTrial ? 'trial' : 'expired'),
            'expires_at' => $hasActiveSubscription ? $subscription->expires_at?->toIso8601String() : $company->trial_ends_at?->toIso8601String(),
            'days_left' => $isTrial ? $trialDaysLeft : $subscriptionDaysLeft,
            'trial_days_left' => $trialDaysLeft,
            'wallet_balance' => (float) $wallet->balance,
            'wallet_currency' => $wallet->currency,
            'usage' => [
                'products' => [
                    'used' => $productsCount,
                    'limit' => $productLimit,
                    'percentage' => $productLimit ? min(100, round(($productsCount / $productLimit) * 100)) : 0,
                    'unlimited' => is_null($productLimit),
                ],
                'orders' => [
                    'used' => $ordersCount,
                    'limit' => $orderLimit,
                    'percentage' => $orderLimit ? min(100, round(($ordersCount / $orderLimit) * 100)) : 0,
                    'unlimited' => is_null($orderLimit),
                ],
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain,
                'status' => $company->status,
                'trial_ends_at' => $company->trial_ends_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Purchase / upgrade to a subscription plan using wallet funds.
     */
    public function purchaseSubscription(Company $company, int|string $planId, string $billingCycle = 'monthly'): array
    {
        return DB::transaction(function () use ($company, $planId, $billingCycle) {
            $plan = SubscriptionPlan::findOrFail($planId);

            if (!$plan->is_active) {
                throw new HttpException(403,'The selected subscription plan is currently unavailable.');
            }

            // Calculate price based on billing cycle
            $price = (float) $plan->price;
            if ($billingCycle === 'yearly') {
                // 20% discount on yearly purchase (12 months * 0.8)
                $price = round($price * 12 * 0.8, 2);
            }

            $wallet = $this->walletService->resolveWallet($company);

            // Deduct funds from wallet if plan price > 0
            $walletTx = null;
            if ($price > 0) {
                if ($wallet->status === 'frozen') {
                    throw new Exception('Your company wallet is frozen. Subscription purchase is restricted. Please contact support.');
                }

                if ($wallet->status === 'inactive') {
                    throw new Exception('Your company wallet is inactive. Transactions cannot be processed.');
                }

                if ((float) $wallet->balance < $price) {
                    throw new Exception("Insufficient wallet balance. Required: PKR " . number_format($price, 2) . ", Current: PKR " . number_format((float) $wallet->balance, 2) . ". Please deposit funds into your wallet first.");
                }

                $walletTx = $this->walletService->debit(
                    $wallet,
                    $price,
                    'subscription',
                    "Subscription payment for {$plan->name} ({$billingCycle})",
                    'SUB-' . strtoupper(Str::random(8)),
                    [
                        'subscription_plan_id' => $plan->id,
                        'plan_name' => $plan->name,
                        'billing_cycle' => $billingCycle,
                    ]
                );
            }

            // Calculate expiration date
            $expiresAt = ($billingCycle === 'yearly') ? now()->addYear() : now()->addDays(30);

            // Create new active subscription record
            $subscription = CompanySubscription::create([
                'company_id' => $company->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => $billingCycle,
                'amount_paid' => $price,
                'currency' => $wallet->currency ?? 'PKR',
                'payment_method' => 'wallet',
                'starts_at' => now(),
                'expires_at' => $expiresAt,
                'trial_ends_at' => null,
                'auto_renew' => true,
            ]);

            // Update company status to active if was on trial or expired
            if (in_array($company->status, ['trial', 'expired'])) {
                $company->update(['status' => 'active']);
            }

            // Referral Commission Calculation & Distribution
            if ($company->referred_by && $price > 0) {
                $referrer = Company::find($company->referred_by);
                $commissionPercentage = PlatformSetting::getReferralCommissionPercentage();

                if ($referrer && $commissionPercentage > 0) {
                    $commissionAmount = round(($price * $commissionPercentage) / 100, 2);

                    if ($commissionAmount > 0) {
                        $referrerWallet = $this->walletService->getOrCreateWallet($referrer);

                        $refWalletTx = $this->walletService->credit(
                            $referrerWallet,
                            $commissionAmount,
                            'referral_commission',
                            "Referral commission ({$commissionPercentage}%) earned from {$company->name} for {$plan->name} ({$billingCycle}) subscription",
                            'COMM-' . strtoupper(Str::random(8)),
                            [
                                'referred_company_id' => $company->id,
                                'referred_company_name' => $company->name,
                                'subscription_id' => $subscription->id,
                                'subscription_plan_id' => $plan->id,
                                'plan_name' => $plan->name,
                                'billing_cycle' => $billingCycle,
                                'subscription_amount' => $price,
                                'commission_percentage' => $commissionPercentage,
                            ]
                        );

                        ReferralCommission::create([
                            'referrer_company_id' => $referrer->id,
                            'referred_company_id' => $company->id,
                            'company_subscription_id' => $subscription->id,
                            'subscription_plan_id' => $plan->id,
                            'wallet_transaction_id' => $refWalletTx->id,
                            'subscription_amount' => $price,
                            'commission_percentage' => $commissionPercentage,
                            'commission_amount' => $commissionAmount,
                            'currency' => $wallet->currency ?? 'PKR',
                            'status' => 'credited',
                            'notes' => "Earned {$commissionPercentage}% commission on {$plan->name} ({$billingCycle}) subscription",
                        ]);
                    }
                }
            }

            return [
                'subscription' => $subscription->load('plan'),
                'transaction' => $walletTx,
                'wallet_balance' => (float) $wallet->fresh()->balance,
            ];
        });
    }

    /**
     * Get past subscription invoices / transaction history.
     */
    public function getInvoices(Company $company)
    {
        return WalletTransaction::where('company_id', $company->id)
            ->where('type', 'debit')
            ->where(function ($q) {
                $q->where('source', 'subscription')
                    ->orWhere('description', 'like', '%subscription%')
                    ->orWhere('reference_id', 'like', 'SUB-%');
            })
            ->orderBy('id', 'desc')
            ->get();
    }
}
