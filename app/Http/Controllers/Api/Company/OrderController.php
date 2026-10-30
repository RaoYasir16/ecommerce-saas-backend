<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\DashboardServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected DashboardServices $dashboardService
    ) {}

    /**
     * Get all merchant orders.
     */
    public function index(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Merchant company not found.', null, 404);
        }

        $orders = $this->dashboardService->getOrders(
            $company,
            $request->only(['search', 'status', 'payment_status', 'per_page'])
        );

        return $this->apiResponse(true, 'Orders retrieved successfully.', $orders);
    }

    /**
     * Get single order by ID or order_number.
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Merchant company not found.', null, 404);
        }

        $order = $this->dashboardService->getOrder($company, $id);

        if (!$order) {
            return $this->apiResponse(false, 'Order not found.', null, 404);
        }

        return $this->apiResponse(true, 'Order retrieved successfully.', $order);
    }

    /**
     * Update order fulfillment or payment status.
     */
    public function updateStatus(Request $request, int|string $id): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,processing,shipped,delivered,cancelled,unfulfilled'],
            'payment_status' => ['nullable', 'string', 'in:pending,paid,failed,refunded'],
        ]);

        $company = auth()->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Merchant company not found.', null, 404);
        }

        try {
            $updatedOrder = $this->dashboardService->updateOrderStatus(
                $company,
                $id,
                $request->only(['status', 'payment_status'])
            );

            return $this->apiResponse(true, 'Order status updated successfully.', $updatedOrder);
        } catch (\Throwable $e) {
            return $this->apiResponse(false, $e->getMessage() ?: 'Unable to update order status.', null, 400);
        }
    }
}
