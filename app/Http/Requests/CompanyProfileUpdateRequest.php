<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyProfileUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150',],

            'email' => ['nullable', 'string', 'email', 'max:255',],

            'whatsapp_number' => ['nullable', 'string', 'max:20',],

            'address' => ['nullable', 'string', 'max:500',],

            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048',],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'name.max' => 'Company name cannot exceed 150 characters.',

            'email.email' => 'Please provide a valid email address.',

            'whatsapp_number.max' => 'WhatsApp number cannot exceed 20 characters.',

            'address.max' => 'Address cannot exceed 500 characters.',

            'logo.image' => 'The logo must be a valid image.',
            'logo.mimes' => 'Logo must be jpeg, png, jpg, webp, or svg.',
            'logo.max' => 'Logo size cannot exceed 2MB.',
        ];
    }
}
