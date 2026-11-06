<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class StoreSetting extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('store_setting')
            ->setDescriptionForEvent(fn(string $eventName) => "Store settings were {$eventName}");
    }

    protected $fillable = [
        'company_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'header_bg_color',
        'footer_bg_color',
        'banners',
        'use_custom_terms',
        'terms_and_conditions',
        'support_email',
        'support_phone',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'show_announcement',
        'announcement_text',
        'footer_text',
    ];
    protected $casts = [
        'banners' => 'array',
        'use_custom_terms' => 'boolean',
        'show_announcement' => 'boolean',
    ];

    public function getBannersAttribute($value)
    {
        $banners = is_array($value)
            ? $value
            : (json_decode($value, true) ?? []);

        return array_map(function ($banner) {
            if (!empty($banner['image_path'])) {
                $banner['image_url'] = asset(
                    'storage/' . $banner['image_path']
                );
            }

            return $banner;
        }, $banners);
    }

    public function tenant()
    {
        return $this->belongsTo(Company::class);
    }
}