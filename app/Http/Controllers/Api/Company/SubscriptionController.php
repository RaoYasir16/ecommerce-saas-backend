<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\SubscriptionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Get all active subscription plans.
     */
    public function plans(): JsonResponse
    {
        $plans = $this->subscriptionService->getPlans();

        return $this->apiResponse(
            true,
            'Subscription plans fetched successfully.',
            $plans,
            200
        );
    }

    /**
     * Get current store subscription details, usage quotas, and trial state.
     */
    public function current(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Company not found.', null, 404);
        }

        $subscriptionData = $this->subscriptionService->getCurrentSubscription($company);

        return $this->apiResponse(
            true,
            'Current subscription details fetched successfully.',
            $subscriptionData,
            200
        );
    }

    /**
     * Purchase / upgrade subscription plan using company wallet funds.
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $company = $request->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Company not found.', null, 404);
        }

        try {
            $billingCycle = $request->input('billing_cycle', 'monthly');
            $result = $this->subscriptionService->purchaseSubscription(
                $company,
                $request->input('plan_id'),
                $billingCycle
            );

            return $this->apiResponse(
                true,
                'Subscription plan upgraded successfully!',
                $result,
                200
            );
        } catch (Exception $e) {
            return $this->apiResponse(
                false,
                $e->getMessage(),
                null,
                422
            );
        }
    }

    /**
     * Get subscription invoices and payment history.
     */
    public function invoices(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Company not found.', null, 404);
        }

        $invoices = $this->subscriptionService->getInvoices($company);

        return $this->apiResponse(
            true,
            'Subscription invoices fetched successfully.',
            $invoices,
            200
        );
    }
}
