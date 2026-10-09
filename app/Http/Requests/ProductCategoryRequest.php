<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
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
        $companyId = auth()->user()->company_id;

        return [
            'name' => ['required','string','max:100',],

            'slug' => ['required','string','max:150',
                Rule::unique('product_categories', 'slug')->where('company_id', $companyId),
            ],

            'description' => ['nullable','string',],

            'image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120',],

            'is_active' => ['nullable','boolean',],
        ];
    }
}
