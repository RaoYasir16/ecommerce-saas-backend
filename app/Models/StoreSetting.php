<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasFactory;

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
        'footer_text'
    ];

    protected $casts = [
        'banners' => 'array',
        'use_custom_terms' => 'boolean',
        'show_announcement' => 'boolean',
    ];

    // Banners mein stored relative paths ko frontend ke liye direct full URL bana kar return karein
    public function getBannersAttribute($value)
    {
        $banners = json_decode($value, true) ?? [];
        return array_map(function ($banner) {
            if (isset($banner['image_path'])) {
                $banner['image_url'] = asset('storage/' . $banner['image_path']);
            }
            return $banner;
        }, $banners);
    }

    public function tenant()
    {
        return $this->belongsTo(Company::class);
    }
}
