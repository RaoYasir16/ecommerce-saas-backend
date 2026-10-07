<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyRegisterRequest extends FormRequest
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
            // User Fields
            'name'                  => 'required|string|max:150',
            'email'                 => 'required|string|email|max:255|unique:users,email',
            'password'              => 'required|string|min:6',
            'password_confirmation' => ['required','same:password'],

            // Company Fields
            'company_name'          => 'required|string|max:150',
            'subdomain'             => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // lowercase letters, numbers, hyphens only
                'unique:companies,subdomain',
                'not_in:admin,api,www,mail,dashboard,root,support', // reserved subdomains
            ],
            'whatsapp_number'       => 'required|string|max:20',
            'logo'                  => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ];
    }
    public function messages(): array
    {
        return [
            'subdomain.regex'   => 'Subdomain can only contain lowercase letters, numbers, and hyphens.',
            'subdomain.unique'  => 'This shop link (subdomain) is already taken. Please choose another.',
            'subdomain.not_in'  => 'This subdomain is reserved and cannot be used.',
        ];
    }
}
