<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plan = $this->route('subscription_plan');
        $planId = $plan instanceof SubscriptionPlan ? $plan->id : $plan;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('subscription_plans', 'slug')->ignore($planId),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'billing_cycle' => ['sometimes', 'required', 'string', 'in:monthly,yearly,lifetime'],
            'currency' => ['nullable', 'string', 'max:10'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'product_limit' => ['nullable', 'integer', 'min:0'],
            'order_limit' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
            'is_popular' => ['nullable', 'boolean'],
        ];
    }
}
