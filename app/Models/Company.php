<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'logo',
        'whatsapp_number',
        'email',
        'address',
        'trial_ends_at',
        'status',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get complete logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return Storage::disk('public')->url($this->logo);
    }
}