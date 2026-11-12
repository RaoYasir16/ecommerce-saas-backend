<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\WholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class WholesaleOrderController extends Controller
{
    public function __construct(
        protected WholesaleService $wholesaleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = auth()->user()?->company;
        if (!$company) {
            return $this->apiResponse(false, 'Company not found.', null, 404);
        }

        $orders = $this->wholesaleService->listOrders(
            $request->only(['search', 'status', 'payment_status', 'per_page']),
            $company->id
        );

        return $this->apiResponse(true, 'Company wholesale orders fetched successfully.', $orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wholesale_product_id' => ['required', 'integer', 'exists:wholesale_products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'selected_color' => ['nullable', 'string', 'max:100'],
            'selected_size' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:Bank Transfer,Cash on Delivery,Merchant Wallet'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'whatsapp_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = auth()->user();
        $company = $user?->company;

        try {
            $order = $this->wholesaleService->placeMerchantOrder($company, $user, $validated);

            return $this->apiResponse(true, 'Wholesale order submitted successfully.', $order, 201);
        } catch (ValidationException $e) {
            return $this->apiResponse(false, $e->getMessage(), ['errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to submit wholesale order: ' . $e->getMessage(), null, 500);
        }
    }
}

