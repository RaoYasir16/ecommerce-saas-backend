<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyWallet extends Model
{
    use HasFactory;

    protected $table = 'company_wallet';

    protected $fillable = [
        'company_id',
        'balance',
        'currency',
        'status',
        'status_reason',
        'status_updated_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'status_updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'company_wallet_id');
    }

    public function depositRequests(): HasMany
    {
        return $this->hasMany(WalletDepositRequest::class, 'company_wallet_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }
}
