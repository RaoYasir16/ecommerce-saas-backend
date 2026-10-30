<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $table = 'transaction_history';

    protected $fillable = [
        'company_wallet_id',
        'company_id',
        'transaction_number',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'source',
        'reference_id',
        'description',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = 'TXN-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CompanyWallet::class, 'company_wallet_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
