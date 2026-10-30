<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $table = 'platform_settings';

    protected $fillable = [
        'referral_commission_percentage',
        'is_referral_enabled',
        'currency',
        'store_footer_copyright_text',
        'additional_settings',
    ];

    protected $casts = [
        'referral_commission_percentage' => 'float',
        'is_referral_enabled' => 'boolean',
        'additional_settings' => 'array',
    ];

    /**
     * Get or create the singleton platform setting instance.
     */
    public static function instance(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'referral_commission_percentage' => 5.00,
                'is_referral_enabled' => true,
                'currency' => 'PKR',
                'store_footer_copyright_text' => '© 2026 storefront. All rights reserved.',
                'additional_settings' => null,
            ]
        );
    }

    /**
     * Get the active referral commission percentage set by Super Admin.
     */
    public static function getReferralCommissionPercentage(): float
    {
        $setting = static::first();
        if ($setting && $setting->is_referral_enabled) {
            return (float) $setting->referral_commission_percentage;
        }

        return $setting ? (float) $setting->referral_commission_percentage : 5.00;
    }

    /**
     * Get the store footer copyright text configured by Super Admin.
     */
    public static function getStoreFooterCopyrightText(): string
    {
        $setting = static::first();
        return $setting?->store_footer_copyright_text ?: '© 2026 storefront. All rights reserved.';
    }
}
