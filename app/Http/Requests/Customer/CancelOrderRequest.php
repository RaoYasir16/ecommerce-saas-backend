<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer'],
            'id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
