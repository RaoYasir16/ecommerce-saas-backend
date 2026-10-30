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
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (empty($this->slug) && !empty($this->title)) {
            $this->merge([
                'slug' => \Illuminate\Support\Str::slug($this->title),
            ]);
        }

        if ($this->has('has_variants')) {
            $this->merge([
                'has_variants' => filter_var($this->has_variants, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('variants_json') && (!$this->has('variants') || empty($this->variants))) {
            $decoded = json_decode($this->variants_json, true);
            if (is_array($decoded)) {
                $this->merge(['variants' => $decoded]);
            }
        }

        if ($this->has('variants')) {
            $variants = $this->variants;
            if (is_string($variants)) {
                $variants = json_decode($variants, true) ?? [];
            }
            if (is_array($variants)) {
                foreach ($variants as $k => $v) {
                    if (isset($v['attributes']) && is_string($v['attributes'])) {
                        $variants[$k]['attributes'] = json_decode($v['attributes'], true) ?? [];
                    }
                }
                $this->merge(['variants' => $variants]);
            }
        }
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
                'required_if:has_variants,1,true',
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
            'is_featured' =>  ['nullable', 'boolean'],
        ];
    }
}
