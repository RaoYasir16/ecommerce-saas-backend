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
        'hero_type',
        'hero_badge',
        'hero_title',
        'hero_subtitle',
        'hero_image',
        'hero_button_text',
        'hero_button_link',
        'hero_features',
        'use_custom_terms',
        'terms_and_conditions',
        'support_email',
        'support_phone',
        'city',
        'province',
        'country',
        'google_map_url',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'show_announcement',
        'announcement_text',
        'footer_text',
    ];

    protected $casts = [
        'banners' => 'array',
        'hero_features' => 'array',
        'use_custom_terms' => 'boolean',
        'show_announcement' => 'boolean',
    ];

    protected $appends = [
        'hero_image_url',
        'store_location',
    ];

    public function getStoreLocationAttribute(): ?string
    {
        $parts = array_values(array_filter([$this->city, $this->province, $this->country]));
        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    public function getHeroImageUrlAttribute()
    {
        if (empty($this->hero_image)) {
            return null;
        }

        if (str_starts_with($this->hero_image, 'http://') || str_starts_with($this->hero_image, 'https://')) {
            return $this->hero_image;
        }

        $cleanPath = ltrim(preg_replace('#^storage/#', '', $this->hero_image), '/');

        return asset('storage/' . $cleanPath);
    }

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