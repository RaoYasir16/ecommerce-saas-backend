<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Company\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CompanyWalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Resolve company instance from param.
     */
    protected function resolveCompany(Company|int|string $company): Company
    {
        return $company instanceof Company ? $company : Company::findOrFail($company);
    }

    /**
     * Show company wallet details and recent transactions.
     */
    public function show(Company|int|string $company): JsonResponse
    {
        try {
            $companyInstance = $this->resolveCompany($company);
            $wallet = $this->walletService->getOrCreateWallet($companyInstance);

            return $this->apiResponse(
                true,
                'Company wallet details fetched successfully.',
                [
                    'wallet' => $wallet,
                    'company' => [
                        'id' => $companyInstance->id,
                        'name' => $companyInstance->name,
                        'subdomain' => $companyInstance->subdomain,
                    ],
                ]
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to fetch company wallet: ' . $e->getMessage(),
                null,
                404
            );
        }
    }

    /**
     * Update wallet status by platform (active, frozen, inactive).
     */
    public function updateStatus(Request $request, Company|int|string $company): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:active,frozen,inactive'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $companyInstance = $this->resolveCompany($company);

            $wallet = $this->walletService->updateStatus(
                $companyInstance,
                $request->input('status'),
                $request->input('reason')
            );

            return $this->apiResponse(
                true,
                "Company wallet status updated to {$wallet->status} successfully.",
                $wallet
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to update wallet status: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Adjust wallet balance directly by platform admin (credit or debit).
     */
    public function adjustBalance(Request $request, Company|int|string $company): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        try {
            $companyInstance = $this->resolveCompany($company);
            $type = $request->input('type');
            $amount = (float) $request->input('amount');
            $description = $request->input('description');

            if ($type === 'credit') {
                $transaction = $this->walletService->credit(
                    $companyInstance,
                    $amount,
                    'platform_adjustment',
                    $description
                );
            } else {
                $transaction = $this->walletService->debit(
                    $companyInstance,
                    $amount,
                    'platform_adjustment',
                    $description
                );
            }

            return $this->apiResponse(
                true,
                'Wallet balance adjusted successfully.',
                [
                    'transaction' => $transaction,
                    'wallet' => $this->walletService->getBalance($companyInstance),
                ]
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to adjust wallet balance: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Get paginated transactions for this company's wallet.
     */
    public function transactions(Request $request, Company|int|string $company): JsonResponse
    {
        try {
            $companyInstance = $this->resolveCompany($company);

            $transactions = $this->walletService->getTransactions(
                $companyInstance,
                $request->only(['search', 'type', 'source', 'status', 'from_date', 'to_date', 'per_page'])
            );

            return $this->apiResponse(
                true,
                'Company wallet transactions fetched successfully.',
                $transactions
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to fetch transactions: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Get all merchant deposit requests across platform.
     */
    public function depositRequests(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'company_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $deposits = $this->walletService->getAllDepositRequests(
                $request->only(['status', 'company_id', 'search', 'per_page'])
            );

            return $this->apiResponse(
                true,
                'Deposit requests fetched successfully.',
                $deposits
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to fetch deposit requests: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Approve a deposit request and credit company wallet.
     */
    public function approveDeposit(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $admin = auth('admin')->user();
            $adminId = $admin ? $admin->id : null;

            $result = $this->walletService->approveDepositRequest(
                $id,
                $adminId,
                $request->input('admin_notes')
            );

            return $this->apiResponse(
                true,
                'Deposit request approved successfully and funds credited to company wallet.',
                $result
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to approve deposit: ' . $e->getMessage(),
                null,
                400
            );
        }
    }

    /**
     * Reject a deposit request with reason.
     */
    public function rejectDeposit(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $admin = auth('admin')->user();
            $adminId = $admin ? $admin->id : null;

            $deposit = $this->walletService->rejectDepositRequest(
                $id,
                $adminId,
                $request->input('reason')
            );

            return $this->apiResponse(
                true,
                'Deposit request rejected successfully.',
                $deposit
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to reject deposit: ' . $e->getMessage(),
                null,
                400
            );
        }
    }
}
