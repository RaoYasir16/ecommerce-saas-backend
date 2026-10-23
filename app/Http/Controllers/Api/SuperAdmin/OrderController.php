<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SuperAdmin\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Display a listing of orders across all companies.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getAllOrders(
            $request->only([
                'search',
                'company_id',
                'user_id',
                'status',
                'payment_status',
                'payment_method',
                'start_date',
                'end_date',
                'per_page',
            ])
        );

        return $this->apiResponse(
            true,
            'Orders fetched successfully.',
            $orders
        );
    }

    /**
     * Display the specified order details.
     */
    public function show(Order|int|string $order): JsonResponse
    {
        $orderDetails = $this->orderService->getOrderDetails($order);

        return $this->apiResponse(
            true,
            'Order details fetched successfully.',
            $orderDetails
        );
    }
}
