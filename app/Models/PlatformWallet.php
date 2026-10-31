<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlatformWallet extends Model
{
    use HasFactory;

    protected $table = 'platform_wallet';

    protected $fillable = [
        'balance',
        'currency',
        'total_revenue',
        'total_commission_paid',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_revenue' => 'decimal:2',
        'total_commission_paid' => 'decimal:2',
    ];

    /**
     * Singleton instance accessor.
     */
    public static function instance(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'balance' => 0.00,
                'currency' => 'PKR',
                'total_revenue' => 0.00,
                'total_commission_paid' => 0.00,
            ]
        );
    }

    /**
     * Transactions belonging to the platform wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PlatformWalletTransaction::class, 'id', 'id'); // All platform transactions
    }

    /**
     * Credit funds into the platform wallet (e.g. from subscription sale after referral commission deduction).
     */
    public function credit(
        float $netAmount,
        float $grossAmount,
        float $commissionDeducted = 0.00,
        string $source = 'subscription_sale',
        ?string $description = null,
        ?Company $company = null,
        ?string $referenceId = null,
        ?array $metadata = null
    ): PlatformWalletTransaction {
        $balanceBefore = (float) $this->balance;
        $balanceAfter = round($balanceBefore + $netAmount, 2);

        $this->update([
            'balance' => $balanceAfter,
            'total_revenue' => round((float) $this->total_revenue + $grossAmount, 2),
            'total_commission_paid' => round((float) $this->total_commission_paid + $commissionDeducted, 2),
        ]);

        return PlatformWalletTransaction::create([
            'transaction_number' => 'PWTXN-' . strtoupper(Str::random(10)),
            'type' => 'credit',
            'amount' => $netAmount,
            'gross_amount' => $grossAmount,
            'commission_deducted' => $commissionDeducted,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'source' => $source,
            'company_id' => $company?->id,
            'company_name' => $company?->name,
            'description' => $description,
            'reference_id' => $referenceId,
            'currency' => $this->currency ?? 'PKR',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Debit funds from the platform wallet (e.g. manual adjustment or platform withdrawal).
     */
    public function debit(
        float $amount,
        string $source = 'manual_adjustment',
        ?string $description = null,
        ?array $metadata = null
    ): PlatformWalletTransaction {
        $balanceBefore = (float) $this->balance;
        if ($balanceBefore < $amount) {
            throw new Exception("Insufficient platform wallet balance. Available: {$this->currency} " . number_format($balanceBefore, 2));
        }

        $balanceAfter = round($balanceBefore - $amount, 2);

        $this->update([
            'balance' => $balanceAfter,
        ]);

        return PlatformWalletTransaction::create([
            'transaction_number' => 'PWTXN-' . strtoupper(Str::random(10)),
            'type' => 'debit',
            'amount' => $amount,
            'gross_amount' => $amount,
            'commission_deducted' => 0.00,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'source' => $source,
            'company_id' => null,
            'company_name' => null,
            'description' => $description,
            'reference_id' => null,
            'currency' => $this->currency ?? 'PKR',
            'metadata' => $metadata,
        ]);
    }
}
