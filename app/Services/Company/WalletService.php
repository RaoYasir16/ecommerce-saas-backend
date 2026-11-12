<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\CompanyWallet;
use App\Models\PlatformSetting;
use App\Models\PlatformWallet;
use App\Models\WalletDepositRequest;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawalRequest;
use App\Traits\FileUploadTrait;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    use FileUploadTrait;
    /**
     * Resolve wallet instance from CompanyWallet, Company, or company_id.
     */
    public function resolveWallet(CompanyWallet|Company|int|string $walletOrCompany): CompanyWallet
    {
        if ($walletOrCompany instanceof CompanyWallet) {
            return $walletOrCompany;
        }

        if ($walletOrCompany instanceof Company) {
            return $this->getOrCreateWallet($walletOrCompany);
        }

        $wallet = CompanyWallet::where('company_id', $walletOrCompany)->first();
        if ($wallet) {
            return $wallet;
        }

        $companyCurrency = Company::where('id', $walletOrCompany)->value('currency') ?: 'PKR';

        return CompanyWallet::firstOrCreate(
            ['company_id' => $walletOrCompany],
            [
                'balance' => 0.00,
                'currency' => $companyCurrency,
                'status' => 'active',
                'status_reason' => null,
                'status_updated_at' => now(),
            ]
        );
    }

    /**
     * Get or lazy-create wallet for a company.
     */
    public function getOrCreateWallet(Company|int|string $company): CompanyWallet
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        $companyCurrency = $company instanceof Company
            ? ($company->currency ?: 'PKR')
            : (Company::where('id', $companyId)->value('currency') ?: 'PKR');

        return CompanyWallet::firstOrCreate(
            ['company_id' => $companyId],
            [
                'balance' => 0.00,
                'currency' => $companyCurrency,
                'status' => 'active',
                'status_reason' => null,
                'status_updated_at' => now(),
            ]
        );
    }

    /**
     * Get company wallet balance and details directly from company_wallet table.
     */
    public function getBalance(CompanyWallet|Company|int|string $walletOrCompany): array
    {
        $wallet = $this->resolveWallet($walletOrCompany);
        $wallet->loadMissing('company:id,currency');
        $effectiveCurrency = $wallet->company?->currency ?: $wallet->currency ?: 'PKR';

        return [
            'id' => $wallet->id,
            'company_id' => $wallet->company_id,
            'balance' => (float) $wallet->balance,
            'currency' => $effectiveCurrency,
            'status' => $wallet->status,
            'status_reason' => $wallet->status_reason,
            'status_updated_at' => $wallet->status_updated_at,
            'withdrawal_fee_percentage' => PlatformSetting::getWithdrawalFeePercentage(),
            'created_at' => $wallet->created_at,
            'updated_at' => $wallet->updated_at,
        ];
    }

    /**
     * Get paginated transactions for company's wallet directly from transaction_history table.
     */
    public function getTransactions(CompanyWallet|Company|int|string $walletOrCompany, array $filters = []): LengthAwarePaginator
    {
        $wallet = $this->resolveWallet($walletOrCompany);

        $query = WalletTransaction::query()
            ->where('company_wallet_id', $wallet->id);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Credit amount into company wallet.
     */
    public function credit(
        CompanyWallet|Company|int|string $walletOrCompany,
        float $amount,
        string $source = 'deposit',
        ?string $description = null,
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new Exception('Credit amount must be greater than zero.');
        }

        $resolvedWallet = $this->resolveWallet($walletOrCompany);

        return DB::transaction(function () use ($resolvedWallet, $amount, $source, $description, $referenceId, $metadata) {
            $wallet = CompanyWallet::where('id', $resolvedWallet->id)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = $resolvedWallet;
            }

            if ($wallet->status === 'inactive') {
                throw new Exception('Wallet is inactive. Transactions cannot be processed.');
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            $wallet->update([
                'balance' => $balanceAfter,
            ]);

            return WalletTransaction::create([
                'company_wallet_id' => $wallet->id,
                'company_id' => $wallet->company_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'source' => $source,
                'reference_id' => $referenceId,
                'description' => $description,
                'status' => 'completed',
                'metadata' => !empty($metadata) ? $metadata : null,
            ]);
        });
    }

    /**
     * Debit amount from company wallet.
     */
    public function debit(
        CompanyWallet|Company|int|string $walletOrCompany,
        float $amount,
        string $source = 'withdrawal',
        ?string $description = null,
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new Exception('Debit amount must be greater than zero.');
        }

        $resolvedWallet = $this->resolveWallet($walletOrCompany);

        return DB::transaction(function () use ($resolvedWallet, $amount, $source, $description, $referenceId, $metadata) {
            $wallet = CompanyWallet::where('id', $resolvedWallet->id)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = $resolvedWallet;
            }

            if ($wallet->status === 'frozen') {
                throw new Exception('Wallet is frozen by the platform. Withdrawals and debits are restricted.');
            }

            if ($wallet->status === 'inactive') {
                throw new Exception('Wallet is inactive. Transactions cannot be processed.');
            }

            $balanceBefore = (float) $wallet->balance;

            if ($balanceBefore < $amount) {
                throw new Exception('Insufficient wallet balance.');
            }

            $balanceAfter = $balanceBefore - $amount;

            $wallet->update([
                'balance' => $balanceAfter,
            ]);

            return WalletTransaction::create([
                'company_wallet_id' => $wallet->id,
                'company_id' => $wallet->company_id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'source' => $source,
                'reference_id' => $referenceId,
                'description' => $description,
                'status' => 'completed',
                'metadata' => !empty($metadata) ? $metadata : null,
            ]);
        });
    }

    /**
     * Update wallet status by platform/super-admin.
     */
    public function updateStatus(
        CompanyWallet|Company|int|string $walletOrCompany,
        string $status,
        ?string $reason = null
    ): CompanyWallet {
        $wallet = $this->resolveWallet($walletOrCompany);

        $wallet->update([
            'status' => $status,
            'status_reason' => $reason,
            'status_updated_at' => now(),
        ]);

        return $wallet->fresh();
    }

    /**
     * Get platform payment and QR details for merchant wallet deposit.
     */
    public function getPlatformPaymentDetails(): array
    {
        return [
            'bank_name' => 'Meezan Bank Limited',
            'account_title' => 'ShopFlow SaaS Platform (Pvt) Ltd',
            'account_number' => '01010102938475',
            'iban' => 'PK36MEZN0001010102938475',
            'raast_id' => '03001234567',
            'qr_code_url' => url('assets/platform-qr.svg'),
            'instructions' => [
                'Scan the QR code using any banking app or EasyPaisa / JazzCash.',
                'Or transfer funds directly to the Meezan Bank or Raast account details shown above.',
                'Capture a clear screenshot of the successful transaction receipt.',
                'Enter the transferred amount, attach the screenshot, and submit your deposit request.',
                'Once approved by platform administration, the balance will be instantly credited to your wallet.',
            ],
        ];
    }

    /**
     * Create a wallet deposit request with receipt image upload attached to company_wallet.
     */
    public function createDepositRequest(
        CompanyWallet|Company|int|string $walletOrCompany,
        float $amount,
        UploadedFile $receiptFile,
        ?string $notes = null,
        string $paymentMethod = 'qr_code'
    ): WalletDepositRequest {
        if ($amount <= 0) {
            throw new Exception('Deposit amount must be greater than zero.');
        }

        $wallet = $this->resolveWallet($walletOrCompany);

        if ($wallet->status === 'inactive') {
            throw new Exception('Wallet is inactive. Cannot submit deposit requests.');
        }

        // Upload payment receipt image
        $receiptPath = $this->uploadFile($receiptFile, 'wallet/receipts');

        return WalletDepositRequest::create([
            'company_id' => $wallet->company_id,
            'company_wallet_id' => $wallet->id,
            'amount' => $amount,
            'receipt_image' => $receiptPath,
            'payment_method' => $paymentMethod,
            'notes' => $notes,
            'status' => 'pending',
        ]);
    }

    /**
     * Get paginated deposit requests directly by company_wallet_id.
     */
    public function getDepositRequests(CompanyWallet|Company|int|string $walletOrCompany, array $filters = []): LengthAwarePaginator
    {
        $wallet = $this->resolveWallet($walletOrCompany);

        $query = WalletDepositRequest::query()
            ->where('company_wallet_id', $wallet->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Get all deposit requests across platform for Super Admin.
     */
    public function getAllDepositRequests(array $filters = []): LengthAwarePaginator
    {
        $query = WalletDepositRequest::query()
            ->with(['company:id,name,subdomain,email,whatsapp_number', 'approver:id,name,email']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('company', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Super Admin approves a deposit request and credits the merchant wallet.
     */
    public function approveDepositRequest(
        WalletDepositRequest|int|string $request,
        ?int $adminId = null,
        ?string $adminNotes = null
    ): array {
        return DB::transaction(function () use ($request, $adminId, $adminNotes) {
            $id = $request instanceof WalletDepositRequest ? $request->id : $request;
            $deposit = WalletDepositRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($deposit->status !== 'pending') {
                throw new Exception("Deposit request has already been {$deposit->status}.");
            }

            $company = $deposit->company;
            if (!$company) {
                throw new Exception('Associated company not found.');
            }

            // Credit the exact amount into company wallet
            $transaction = $this->credit(
                $company,
                (float) $deposit->amount,
                'deposit',
                $adminNotes ?: "Deposit approved (Ref: {$deposit->request_number})",
                $deposit->request_number,
                [
                    'deposit_request_id' => $deposit->id,
                    'approved_by' => $adminId,
                ]
            );

            // Update request status to approved
            $deposit->update([
                'status' => 'approved',
                'admin_notes' => $adminNotes,
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            return [
                'deposit' => $deposit->fresh(['approver']),
                'transaction' => $transaction,
                'wallet' => $this->getBalance($company),
            ];
        });
    }

    /**
     * Super Admin rejects a deposit request with reason.
     */
    public function rejectDepositRequest(
        WalletDepositRequest|int|string $request,
        ?int $adminId = null,
        string $reason = 'Payment verification failed'
    ): WalletDepositRequest {
        return DB::transaction(function () use ($request, $adminId, $reason) {
            $id = $request instanceof WalletDepositRequest ? $request->id : $request;
            $deposit = WalletDepositRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($deposit->status !== 'pending') {
                throw new Exception("Deposit request has already been {$deposit->status}.");
            }

            $deposit->update([
                'status' => 'rejected',
                'admin_notes' => $reason,
                'approved_by' => $adminId,
                'rejected_at' => now(),
            ]);

            return $deposit->fresh(['approver']);
        });
    }

    // =========================================================================
    // WITHDRAWAL REQUESTS & PLATFORM FEE PROCESSING
    // =========================================================================

    /**
     * Merchant submits a withdrawal request.
     * Applies the active platform withdrawal fee percentage (default 2%),
     * debits the requested amount from wallet balance into pending hold,
     * and creates a pending debit transaction ledger entry.
     */
    public function createWithdrawalRequest(
        CompanyWallet|Company|int|string $walletOrCompany,
        float $requestedAmount,
        array $payoutDetails
    ): array {
        if ($requestedAmount <= 0) {
            throw new Exception('Withdrawal amount must be greater than zero.');
        }

        $resolvedWallet = $this->resolveWallet($walletOrCompany);

        return DB::transaction(function () use ($resolvedWallet, $requestedAmount, $payoutDetails) {
            $wallet = CompanyWallet::where('id', $resolvedWallet->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = $resolvedWallet;
            }

            if ($wallet->status === 'frozen') {
                throw new Exception('Your wallet is frozen by the platform. Withdrawals are currently paused.');
            }

            if ($wallet->status === 'inactive') {
                throw new Exception('Wallet is inactive. Cannot submit withdrawal requests.');
            }

            $balanceBefore = (float) $wallet->balance;
            if ($balanceBefore < $requestedAmount) {
                throw new Exception('Insufficient wallet balance for this withdrawal.');
            }

            $feePercentage = PlatformSetting::getWithdrawalFeePercentage();
            $feeAmount = round($requestedAmount * ($feePercentage / 100), 2);
            $netAmount = round($requestedAmount - $feeAmount, 2);

            if ($netAmount <= 0) {
                throw new Exception('Net payout amount must be greater than zero after deducting platform fee.');
            }

            $withdrawal = WalletWithdrawalRequest::create([
                'company_id' => $wallet->company_id,
                'company_wallet_id' => $wallet->id,
                'requested_amount' => $requestedAmount,
                'fee_percentage' => $feePercentage,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,
                'payment_method' => $payoutDetails['payment_method'] ?? 'bank_transfer',
                'bank_name' => $payoutDetails['bank_name'] ?? null,
                'account_title' => $payoutDetails['account_title'],
                'account_number' => $payoutDetails['account_number'],
                'notes' => $payoutDetails['notes'] ?? null,
                'status' => 'pending',
            ]);

            $balanceAfter = round($balanceBefore - $requestedAmount, 2);
            $wallet->update(['balance' => $balanceAfter]);

            $currency = $wallet->currency ?? 'PKR';
            $transaction = WalletTransaction::create([
                'company_wallet_id' => $wallet->id,
                'company_id' => $wallet->company_id,
                'type' => 'debit',
                'amount' => $requestedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'source' => 'withdrawal',
                'reference_id' => $withdrawal->request_number,
                'description' => "Withdrawal request {$withdrawal->request_number} (Fee {$feePercentage}%: {$currency} {$feeAmount}, Net Payout: {$currency} {$netAmount})",
                'status' => 'pending',
                'metadata' => [
                    'withdrawal_request_id' => $withdrawal->id,
                    'fee_percentage' => $feePercentage,
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                    'payment_method' => $withdrawal->payment_method,
                    'account_title' => $withdrawal->account_title,
                    'account_number' => $withdrawal->account_number,
                ],
            ]);

            return [
                'withdrawal' => $withdrawal->fresh(),
                'transaction' => $transaction,
                'wallet' => $this->getBalance($wallet->fresh()),
            ];
        });
    }

    /**
     * Get paginated withdrawal requests for a company wallet.
     */
    public function getWithdrawalRequests(CompanyWallet|Company|int|string $walletOrCompany, array $filters = []): LengthAwarePaginator
    {
        $wallet = $this->resolveWallet($walletOrCompany);

        $query = WalletWithdrawalRequest::query()
            ->where('company_wallet_id', $wallet->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('account_title', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%");
            });
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Get all merchant withdrawal requests across platform for Super Admin.
     */
    public function getAllWithdrawalRequests(array $filters = []): LengthAwarePaginator
    {
        $query = WalletWithdrawalRequest::query()
            ->with(['company:id,name,subdomain,email,whatsapp_number', 'approver:id,name,email']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('account_title', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('company', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('subdomain', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Super Admin approves a withdrawal request, marks ledger TXN completed,
     * and credits the deducted platform fee into PlatformWallet.
     */
    public function approveWithdrawalRequest(
        WalletWithdrawalRequest|int|string $request,
        ?int $adminId = null,
        ?string $adminNotes = null,
        ?string $payoutReference = null,
        ?UploadedFile $receiptFile = null
    ): array {
        return DB::transaction(function () use ($request, $adminId, $adminNotes, $payoutReference, $receiptFile) {
            $id = $request instanceof WalletWithdrawalRequest ? $request->id : $request;
            $withdrawal = WalletWithdrawalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw new Exception("Withdrawal request has already been {$withdrawal->status}.");
            }

            $receiptPath = $withdrawal->payout_receipt;
            if ($receiptFile) {
                $receiptPath = $this->uploadFile($receiptFile, 'wallet/payouts');
            }

            // Complete the pending debit transaction in merchant's wallet ledger
            WalletTransaction::where('company_wallet_id', $withdrawal->company_wallet_id)
                ->where('reference_id', $withdrawal->request_number)
                ->where('source', 'withdrawal')
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'description' => "Withdrawal approved ({$withdrawal->request_number})" . ($payoutReference ? " • Ref: {$payoutReference}" : ''),
                ]);

            // Credit platform withdrawal fee into Platform Master Wallet
            $feeAmount = (float) $withdrawal->fee_amount;
            if ($feeAmount > 0) {
                $platformWallet = PlatformWallet::instance();
                $company = $withdrawal->company;
                $platformWallet->credit(
                    $feeAmount,
                    $feeAmount,
                    0.00,
                    'withdrawal_fee',
                    "Withdrawal Fee ({$withdrawal->fee_percentage}%) from " . ($company?->name ?? "Company #{$withdrawal->company_id}") . " ({$withdrawal->request_number})",
                    $company,
                    $withdrawal->request_number,
                    [
                        'withdrawal_request_id' => $withdrawal->id,
                        'requested_amount' => (float) $withdrawal->requested_amount,
                        'fee_percentage' => (float) $withdrawal->fee_percentage,
                        'net_payout_amount' => (float) $withdrawal->net_amount,
                        'approved_by' => $adminId,
                    ]
                );
            }

            $withdrawal->update([
                'status' => 'approved',
                'admin_notes' => $adminNotes,
                'payout_reference' => $payoutReference,
                'payout_receipt' => $receiptPath,
                'approved_by' => $adminId,
                'approved_at' => now(),
            ]);

            return [
                'withdrawal' => $withdrawal->fresh(['company', 'approver']),
                'wallet' => $this->getBalance($withdrawal->company_id),
            ];
        });
    }

    /**
     * Super Admin rejects a withdrawal request and refunds the held amount
     * back into the merchant's company wallet.
     */
    public function rejectWithdrawalRequest(
        WalletWithdrawalRequest|int|string $request,
        ?int $adminId = null,
        string $reason = 'Withdrawal request rejected by administration'
    ): array {
        return DB::transaction(function () use ($request, $adminId, $reason) {
            $id = $request instanceof WalletWithdrawalRequest ? $request->id : $request;
            $withdrawal = WalletWithdrawalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw new Exception("Withdrawal request has already been {$withdrawal->status}.");
            }

            $company = $withdrawal->company;
            if (!$company) {
                throw new Exception('Associated company not found.');
            }

            // Mark the pending debit transaction as cancelled
            WalletTransaction::where('company_wallet_id', $withdrawal->company_wallet_id)
                ->where('reference_id', $withdrawal->request_number)
                ->where('source', 'withdrawal')
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                ]);

            // Refund held amount back into merchant's wallet
            $refundTransaction = $this->credit(
                $company,
                (float) $withdrawal->requested_amount,
                'withdrawal_refund',
                "Withdrawal rejected & refunded ({$withdrawal->request_number}): {$reason}",
                $withdrawal->request_number,
                [
                    'withdrawal_request_id' => $withdrawal->id,
                    'rejected_by' => $adminId,
                ]
            );

            $withdrawal->update([
                'status' => 'rejected',
                'admin_notes' => $reason,
                'approved_by' => $adminId,
                'rejected_at' => now(),
            ]);

            return [
                'withdrawal' => $withdrawal->fresh(['company', 'approver']),
                'refund_transaction' => $refundTransaction,
                'wallet' => $this->getBalance($company),
            ];
        });
    }
}

