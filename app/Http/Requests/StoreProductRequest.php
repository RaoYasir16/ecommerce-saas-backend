<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('company_id', $companyId),
            ],
            'slug' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-zA-Z0-9-]+$/',
            ],
            'description' => ['nullable', 'string'],
            'regular_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => [
                'nullable',
                'numeric',
                'min:0',
                'lte:regular_price',
            ],
            'stock' => ['nullable', 'integer', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
            'has_variants' => ['nullable', 'boolean'],

            'variants' => [
                'required_if:has_variants,1',
                'array',
            ],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.regular_price' => [
                'nullable', 'numeric', 'min:0',
            ],
            'variants.*.sale_price' => [
                'nullable', 'numeric', 'min:0',
            ],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.attributes' => ['required_with:variants', 'array'],
            'variants.*.is_available' => ['nullable', 'boolean'],

            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }
}
