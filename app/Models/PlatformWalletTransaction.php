<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformWalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'platform_wallet_transactions';

    protected $fillable = [
        'transaction_number',
        'type',
        'amount',
        'gross_amount',
        'commission_deducted',
        'balance_before',
        'balance_after',
        'source',
        'company_id',
        'company_name',
        'description',
        'reference_id',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'commission_deducted' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Associated company/merchant if applicable.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
