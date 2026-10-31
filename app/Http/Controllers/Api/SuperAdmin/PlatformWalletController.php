<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CompanySubscription;
use App\Models\PlatformSetting;
use App\Models\PlatformWallet;
use App\Models\PlatformWalletTransaction;
use App\Models\ReferralCommission;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformWalletController extends Controller
{
    /**
     * Get platform wallet balance, summary metrics & recent ledger transactions.
     */
    public function getWallet(): JsonResponse
    {
        $wallet = PlatformWallet::instance();

        // Active subscriptions count: Currently active & not expired
        $activeSubscriptionsCount = CompanySubscription::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        // Total referral commission paid out across the entire platform
        $totalCommissionPaid = (float) ReferralCommission::where('status', 'credited')->sum('commission_amount');
        if ($totalCommissionPaid > (float) $wallet->total_commission_paid) {
            $wallet->update(['total_commission_paid' => $totalCommissionPaid]);
        }

        // Recent 10 transactions
        $recentTransactions = PlatformWalletTransaction::with('company')
            ->latest('id')
            ->take(10)
            ->get();

        return $this->apiResponse(
            true,
            'Platform wallet fetched successfully.',
            [
                'wallet' => [
                    'id' => $wallet->id,
                    'balance' => (float) $wallet->balance,
                    'currency' => $wallet->currency ?? 'PKR',
                    'total_revenue' => (float) $wallet->total_revenue,
                    'total_commission_paid' => (float) $wallet->total_commission_paid,
                    'active_subscriptions_count' => $activeSubscriptionsCount,
                    'total_transactions_count' => PlatformWalletTransaction::count(),
                    'updated_at' => $wallet->updated_at?->toIso8601String(),
                ],
                'recent_transactions' => $recentTransactions,
            ],
            200
        );
    }

    /**
     * Update the platform admin currency (e.g. PKR, USD, AED, SAR, GBP, EUR).
     */
    public function updateCurrency(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => ['required', 'string', 'max:10'],
        ]);

        $currency = strtoupper(trim($request->input('currency')));

        $wallet = PlatformWallet::instance();
        $wallet->update(['currency' => $currency]);

        // Keep platform settings currency in sync
        $setting = PlatformSetting::instance();
        $setting->update(['currency' => $currency]);

        return $this->apiResponse(
            true,
            "Platform currency successfully updated to {$currency}.",
            [
                'currency' => $currency,
                'wallet' => $wallet->fresh(),
            ],
            200
        );
    }

    /**
     * Manual balance adjustment (Credit or Debit) by Super Admin with audit reason.
     */
    public function adjustBalance(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $wallet = PlatformWallet::instance();
        $type = $request->input('type');
        $amount = round((float) $request->input('amount'), 2);
        $description = trim($request->input('description'));

        try {
            if ($type === 'credit') {
                $tx = $wallet->credit(
                    netAmount: $amount,
                    grossAmount: $amount,
                    commissionDeducted: 0.00,
                    source: 'manual_adjustment',
                    description: "[Super Admin Credit Adjustment] {$description}",
                    metadata: [
                        'admin_id' => $request->user()?->id,
                        'reason' => $description,
                    ]
                );
            } else {
                $tx = $wallet->debit(
                    amount: $amount,
                    source: 'manual_adjustment',
                    description: "[Super Admin Debit Adjustment] {$description}",
                    metadata: [
                        'admin_id' => $request->user()?->id,
                        'reason' => $description,
                    ]
                );
            }

            return $this->apiResponse(
                true,
                "Platform wallet balance adjusted successfully by {$wallet->currency} " . number_format($amount, 2),
                [
                    'wallet' => $wallet->fresh(),
                    'transaction' => $tx,
                ],
                200
            );
        } catch (Exception $e) {
            return $this->apiResponse(
                false,
                $e->getMessage(),
                null,
                422
            );
        }
    }

    /**
     * Get paginated platform wallet ledger transactions with search and filter.
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = PlatformWalletTransaction::with('company')->latest('id');

        // Filter by Type
        if ($request->filled('type') && in_array($request->input('type'), ['credit', 'debit'])) {
            $query->where('type', $request->input('type'));
        }

        // Filter by Source
        if ($request->filled('source') && $request->input('source') !== 'all') {
            $query->where('source', $request->input('source'));
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_id', 'like', "%{$search}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $transactions = $query->paginate($perPage);

        return $this->apiResponse(
            true,
            'Platform wallet transactions fetched successfully.',
            $transactions,
            200
        );
    }
}
