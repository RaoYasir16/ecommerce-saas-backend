<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\ReferralCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Get company's referral stats, code, link, referred merchants, and commission history.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Company not found.', null, 404);
        }

        // Ensure company has a referral code
        $referralCode = $company->getOrCreateReferralCode();
        $commissionPercentage = PlatformSetting::getReferralCommissionPercentage();

        // 1. Total statistics
        $totalReferralsCount = $company->referredCompanies()->count();
        $totalCommissionEarned = (float) ReferralCommission::where('referrer_company_id', $company->id)
            ->where('status', 'credited')
            ->sum('commission_amount');

        $activeSubscribersCount = $company->referredCompanies()
            ->whereHas('subscription', function ($q) {
                $q->where('status', 'active');
            })
            ->count();

        // 2. Referred companies list
        $referredCompanies = $company->referredCompanies()
            ->with(['subscription.plan'])
            ->latest('id')
            ->get()
            ->map(function ($ref) {
                $totalGenerated = (float) ReferralCommission::where('referred_company_id', $ref->id)
                    ->where('status', 'credited')
                    ->sum('commission_amount');

                $subscriptionsBought = ReferralCommission::where('referred_company_id', $ref->id)
                    ->count();

                $activePlan = $ref->subscription && $ref->subscription->status === 'active'
                    ? $ref->subscription->plan?->name
                    : null;

                return [
                    'id' => $ref->id,
                    'name' => $ref->name,
                    'subdomain' => $ref->subdomain,
                    'logo_url' => $ref->logo_url,
                    'status' => $ref->status,
                    'joined_at' => $ref->created_at?->format('Y-m-d H:i:s'),
                    'joined_date' => $ref->created_at?->format('M d, Y'),
                    'active_plan' => $activePlan,
                    'is_subscribed' => !is_null($activePlan),
                    'subscriptions_count' => $subscriptionsBought,
                    'total_commission_generated' => $totalGenerated,
                ];
            });

        // 3. Commission history
        $commissionsHistory = ReferralCommission::with(['referredCompany', 'plan', 'walletTransaction'])
            ->where('referrer_company_id', $company->id)
            ->latest('id')
            ->take(50)
            ->get()
            ->map(function ($comm) {
                return [
                    'id' => $comm->id,
                    'referred_company_id' => $comm->referred_company_id,
                    'referred_company_name' => $comm->referredCompany?->name ?? 'Merchant Store',
                    'referred_subdomain' => $comm->referredCompany?->subdomain,
                    'plan_name' => $comm->plan?->name ?? 'Pro Plan',
                    'subscription_amount' => (float) $comm->subscription_amount,
                    'commission_percentage' => (float) $comm->commission_percentage,
                    'commission_amount' => (float) $comm->commission_amount,
                    'currency' => $comm->currency ?? 'PKR',
                    'status' => $comm->status,
                    'transaction_number' => $comm->walletTransaction?->transaction_number,
                    'created_at' => $comm->created_at?->format('Y-m-d H:i:s'),
                    'date' => $comm->created_at?->format('M d, Y h:i A'),
                ];
            });

        return $this->apiResponse(
            true,
            'Referral data fetched successfully.',
            [
                'referral_code' => $referralCode,
                'commission_percentage' => $commissionPercentage,
                'metrics' => [
                    'total_referrals' => $totalReferralsCount,
                    'total_earnings' => $totalCommissionEarned,
                    'active_subscribers' => $activeSubscribersCount,
                    'commission_percentage' => $commissionPercentage,
                ],
                'referred_companies' => $referredCompanies,
                'commissions_history' => $commissionsHistory,
            ],
            200
        );
    }
}
