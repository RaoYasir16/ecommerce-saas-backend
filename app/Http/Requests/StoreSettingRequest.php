<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'header_bg_color' => ['nullable', 'string', 'max:20'],
            'footer_bg_color' => ['nullable', 'string', 'max:20'],

            // Multiple banner images
            'banners' => ['nullable', 'array'],
            'banners.*' => ['required','image','mimes:jpg,jpeg,png,webp','max:5120',],


            'use_custom_terms' => ['nullable', 'boolean'],
            'terms_and_conditions' => ['nullable', 'string'],

            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],

            'facebook_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'tiktok_url' => ['nullable', 'url', 'max:500'],

            'show_announcement' => ['nullable', 'boolean'],
            'announcement_text' => ['nullable', 'string', 'max:1000'],

            'footer_text' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
