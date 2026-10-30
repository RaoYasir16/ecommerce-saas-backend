<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WalletDepositRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'company_wallet_id',
        'request_number',
        'amount',
        'receipt_image',
        'payment_method',
        'notes',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = [
        'receipt_image_url',
    ];

    protected static function booted(): void
    {
        static::creating(function ($request) {
            if (empty($request->request_number)) {
                $request->request_number = 'DEP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
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
     * Get accessible receipt image URL.
     */
    public function getReceiptImageUrlAttribute(): ?string
    {
        if (!$this->receipt_image) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_image);
    }
}
