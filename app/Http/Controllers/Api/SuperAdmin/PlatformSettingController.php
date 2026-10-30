<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\ReferralCommission;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSettingController extends Controller
{
    /**
     * Get platform settings including referral commission percentage.
     */
    public function getSettings(): JsonResponse
    {
        $setting = PlatformSetting::instance();

        return $this->apiResponse(
            true,
            'Platform settings fetched successfully.',
            $setting,
            200
        );
    }

    /**
     * Update platform settings (e.g. referral commission percentage).
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'referral_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_referral_enabled' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'max:10'],
            'store_footer_copyright_text' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = PlatformSetting::instance();

        $updateData = [];
        if ($request->has('referral_commission_percentage')) {
            $updateData['referral_commission_percentage'] = $request->input('referral_commission_percentage');
        }
        if ($request->has('is_referral_enabled')) {
            $updateData['is_referral_enabled'] = $request->boolean('is_referral_enabled');
        }
        if ($request->has('currency')) {
            $updateData['currency'] = $request->input('currency');
        }
        if ($request->has('store_footer_copyright_text')) {
            $updateData['store_footer_copyright_text'] = $request->input('store_footer_copyright_text');
        }

        $setting->update($updateData);

        return $this->apiResponse(
            true,
            'Platform settings updated successfully.',
            $setting->fresh(),
            200
        );
    }

    /**
     * Platform-wide referral overview for Super Admin.
     */
    public function referralOverview(): JsonResponse
    {
        $setting = PlatformSetting::instance();

        $totalCommissionsPaid = (float) ReferralCommission::where('status', 'credited')->sum('commission_amount');
        $totalReferralsCount = Company::whereNotNull('referred_by')->count();
        $totalCommissionsCount = ReferralCommission::count();

        $recentCommissions = ReferralCommission::with(['referrerCompany', 'referredCompany', 'plan'])
            ->latest('id')
            ->take(20)
            ->get();

        return $this->apiResponse(
            true,
            'Referral platform overview fetched successfully.',
            [
                'settings' => $setting,
                'metrics' => [
                    'total_commissions_paid' => $totalCommissionsPaid,
                    'total_referrals_count' => $totalReferralsCount,
                    'total_commissions_count' => $totalCommissionsCount,
                    'active_commission_percentage' => (float) $setting->referral_commission_percentage,
                ],
                'recent_commissions' => $recentCommissions,
            ],
            200
        );
    }
}
