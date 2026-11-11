<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WalletWithdrawalRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'wallet_withdrawal_requests';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'request_number',
                'requested_amount',
                'fee_percentage',
                'fee_amount',
                'net_amount',
                'payment_method',
                'status',
                'admin_notes',
                'payout_reference',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('wallet_withdrawal')
            ->setDescriptionForEvent(fn(string $eventName) => "Withdrawal request {$this->request_number} was {$eventName}");
    }

    protected $fillable = [
        'company_id',
        'company_wallet_id',
        'request_number',
        'requested_amount',
        'fee_percentage',
        'fee_amount',
        'net_amount',
        'payment_method',
        'bank_name',
        'account_title',
        'account_number',
        'notes',
        'status',
        'admin_notes',
        'payout_reference',
        'payout_receipt',
        'approved_by',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'fee_percentage' => 'float',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = [
        'payout_receipt_url',
    ];

    protected static function booted(): void
    {
        static::creating(function ($request) {
            if (empty($request->request_number)) {
                $request->request_number = 'WDR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CompanyWallet::class, 'company_wallet_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Get accessible payout receipt image URL.
     */
    public function getPayoutReceiptUrlAttribute(): ?string
    {
        if (!$this->payout_receipt) {
            return null;
        }

        return Storage::disk('public')->url($this->payout_receipt);
    }
}

