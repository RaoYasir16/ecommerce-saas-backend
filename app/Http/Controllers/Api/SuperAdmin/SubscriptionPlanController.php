<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreSubscriptionPlanRequest;
use App\Http\Requests\SuperAdmin\UpdateSubscriptionPlanRequest;
use App\Services\SuperAdmin\SubscriptionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        protected SubscriptionPlanService $subscriptionPlanService
    ) {}

    /**
     * Display a listing of subscription plans.
     */
    public function index(Request $request): JsonResponse
    {
        $plans = $this->subscriptionPlanService->getAllPlans(
            $request->only(['search', 'billing_cycle', 'is_active', 'per_page', 'all'])
        );

        return $this->apiResponse(
            true,
            'Subscription plans fetched successfully.',
            $plans
        );
    }

    /**
     * Store a newly created subscription plan in storage.
     */
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        try {
            $plan = $this->subscriptionPlanService->createPlan($request->validated());

            return $this->apiResponse(
                true,
                'Subscription plan created successfully.',
                $plan,
                201
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to create subscription plan: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Display the specified subscription plan.
     */
    public function show(int|string $id): JsonResponse
    {
        $plan = $this->subscriptionPlanService->getPlanById($id);

        return $this->apiResponse(
            true,
            'Subscription plan fetched successfully.',
            $plan
        );
    }

    public function update(UpdateSubscriptionPlanRequest $request,int|string $id): JsonResponse 
    {
        try {
            $plan = $this->subscriptionPlanService->updatePlan(
                $id,
                $request->validated()
            );

            return $this->apiResponse(
                true,
                'Subscription plan updated successfully.',
                $plan
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to update subscription plan: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    public function destroy(int|string $id): JsonResponse
    {
        try {
            $this->subscriptionPlanService->deletePlan($id);

            return $this->apiResponse(
                true,
                'Subscription plan deleted successfully.',
                null
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Failed to delete subscription plan: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
