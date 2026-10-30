<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyWallet;
use App\Services\Company\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    /**
     * Resolve the authenticated company's wallet directly from company_wallet table.
     */
    protected function getAuthenticatedWallet(): ?CompanyWallet
    {
        $user = auth('user')->user();
        $companyId = $user?->company_id;

        if (!$companyId) {
            return null;
        }

        return CompanyWallet::firstOrCreate(
            ['company_id' => $companyId],
            [
                'balance' => 0.00,
                'currency' => 'PKR',
                'status' => 'active',
                'status_reason' => null,
                'status_updated_at' => now(),
            ]
        );
    }

    /**
     * Get merchant wallet balance directly from company_wallet table.
     */
    public function getBalance(Request $request): JsonResponse
    {
        $wallet = $this->getAuthenticatedWallet();

        if (!$wallet) {
            return $this->apiResponse(false, 'Merchant wallet not found.', null, 404);
        }

        try {
            $walletData = $this->walletService->getBalance($wallet);

            return $this->apiResponse(
                true,
                'Wallet balance retrieved successfully.',
                $walletData
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to retrieve wallet balance: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Get merchant wallet transaction history directly from transaction_history table.
     */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->getAuthenticatedWallet();

        if (!$wallet) {
            return $this->apiResponse(false, 'Merchant wallet not found.', null, 404);
        }

        $request->validate([
            'type' => ['nullable', 'string', 'in:credit,debit'],
            'source' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:completed,pending,failed,cancelled'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $transactions = $this->walletService->getTransactions(
                $wallet,
                $request->only(['search', 'type', 'source', 'status', 'from_date', 'to_date', 'per_page'])
            );

            return $this->apiResponse(
                true,
                'Wallet transactions retrieved successfully.',
                $transactions
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to retrieve transactions: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Get platform payment and QR details for making a wallet deposit.
     */
    public function paymentDetails(): JsonResponse
    {
        $details = $this->walletService->getPlatformPaymentDetails();

        return $this->apiResponse(
            true,
            'Platform deposit payment details retrieved successfully.',
            $details
        );
    }

    /**
     * Get merchant's deposit requests list directly by company_wallet_id.
     */
    public function deposits(Request $request): JsonResponse
    {
        $wallet = $this->getAuthenticatedWallet();

        if (!$wallet) {
            return $this->apiResponse(false, 'Merchant wallet not found.', null, 404);
        }

        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $deposits = $this->walletService->getDepositRequests(
                $wallet,
                $request->only(['status', 'search', 'per_page'])
            );

            return $this->apiResponse(
                true,
                'Deposit requests retrieved successfully.',
                $deposits
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to retrieve deposit requests: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Submit a new deposit request attached directly to company_wallet.
     */
    public function createDeposit(Request $request): JsonResponse
    {
        $wallet = $this->getAuthenticatedWallet();

        if (!$wallet) {
            return $this->apiResponse(false, 'Merchant wallet not found.', null, 404);
        }

        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'receipt_image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // max 5MB
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $deposit = $this->walletService->createDepositRequest(
                $wallet,
                (float) $request->input('amount'),
                $request->file('receipt_image'),
                $request->input('notes'),
                $request->input('payment_method', 'qr_code')
            );

            return $this->apiResponse(
                true,
                'Deposit request submitted successfully. It will be credited once verified by administration.',
                $deposit,
                201
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to submit deposit request: ' . $e->getMessage(),
                null,
                400
            );
        }
    }
}
