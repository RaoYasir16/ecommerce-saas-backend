<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WholesaleCatalogController extends Controller
{
    public function __construct(
        protected WholesaleService $wholesaleService
    ) {}

    public function categories(Request $request): JsonResponse
    {
        $categories = $this->wholesaleService->listCategories(
            $request->only(['search', 'all']),
            true
        );

        return $this->apiResponse(true, 'Wholesale categories fetched successfully.', $categories);
    }

    public function products(Request $request): JsonResponse
    {
        $products = $this->wholesaleService->listProducts(
            $request->only(['search', 'category_id', 'category', 'in_stock', 'per_page', 'all']),
            true
        );

        return $this->apiResponse(true, 'Wholesale products fetched successfully.', $products);
    }

    public function showProduct(int $id): JsonResponse
    {
        $product = $this->wholesaleService->getProduct($id, true);

        return $this->apiResponse(true, 'Wholesale product fetched successfully.', $product);
    }
}

