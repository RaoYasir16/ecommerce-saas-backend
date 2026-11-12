<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\WholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class WholesaleController extends Controller
{
    public function __construct(
        protected WholesaleService $wholesaleService
    ) {}

    // ==========================================
    // CATEGORIES
    // ==========================================

    public function indexCategories(Request $request): JsonResponse
    {
        $categories = $this->wholesaleService->listCategories(
            $request->only(['search', 'is_active', 'per_page', 'all'])
        );

        return $this->apiResponse(true, 'Wholesale categories fetched successfully.', $categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $category = $this->wholesaleService->createCategory($validated, $request->file('image'));

            return $this->apiResponse(true, 'Wholesale category created successfully.', $category, 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to create wholesale category: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'remove_image' => ['nullable'],
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = ['nullable', 'image', 'max:5120'];
        }

        $validated = $request->validate($rules);

        try {
            $category = $this->wholesaleService->updateCategory($id, $validated, $request->file('image'));

            return $this->apiResponse(true, 'Wholesale category updated successfully.', $category);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            return $this->apiResponse(false, $e->getMessage(), null, $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to update wholesale category: ' . $e->getMessage(), null, 500);
        }
    }

    public function destroyCategory(int $id): JsonResponse
    {
        try {
            $this->wholesaleService->deleteCategory($id);

            return $this->apiResponse(true, 'Wholesale category deleted successfully.');
        } catch (ValidationException $e) {
            return $this->apiResponse(false, $e->getMessage(), ['errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to delete wholesale category: ' . $e->getMessage(), null, 500);
        }
    }

    // ==========================================
    // PRODUCTS
    // ==========================================

    public function indexProducts(Request $request): JsonResponse
    {
        $products = $this->wholesaleService->listProducts(
            $request->only(['search', 'category_id', 'is_active', 'in_stock', 'per_page', 'all'])
        );

        return $this->apiResponse(true, 'Wholesale products fetched successfully.', $products);
    }

    public function showProduct(int $id): JsonResponse
    {
        $product = $this->wholesaleService->getProduct($id);

        return $this->apiResponse(true, 'Wholesale product fetched successfully.', $product);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $rules = [
            'category_id' => ['required', 'integer', 'exists:wholesale_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'wholesale_price' => ['required', 'numeric', 'min:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'moq' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'colors' => ['nullable'],
            'sizes' => ['nullable'],
            'is_active' => ['nullable'],
            'in_stock' => ['nullable'],
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = ['nullable', 'image', 'max:5120'];
        }

        $validated = $request->validate($rules);

        try {
            $product = $this->wholesaleService->createProduct($validated, $request->file('image'));

            return $this->apiResponse(true, 'Wholesale product created successfully.', $product, 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to create wholesale product: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateProduct(Request $request, int $id): JsonResponse
    {
        $rules = [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:wholesale_categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'wholesale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'retail_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'moq' => ['sometimes', 'required', 'integer', 'min:1'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'colors' => ['nullable'],
            'sizes' => ['nullable'],
            'is_active' => ['nullable'],
            'in_stock' => ['nullable'],
            'remove_image' => ['nullable'],
        ];
        if ($request->hasFile('image')) {
            $rules['image'] = ['nullable', 'image', 'max:5120'];
        }

        $validated = $request->validate($rules);

        try {
            $product = $this->wholesaleService->updateProduct($id, $validated, $request->file('image'));

            return $this->apiResponse(true, 'Wholesale product updated successfully.', $product);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to update wholesale product: ' . $e->getMessage(), null, 500);
        }
    }

    public function destroyProduct(int $id): JsonResponse
    {
        try {
            $this->wholesaleService->deleteProduct($id);

            return $this->apiResponse(true, 'Wholesale product deleted successfully.');
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to delete wholesale product: ' . $e->getMessage(), null, 500);
        }
    }

    // ==========================================
    // ORDERS
    // ==========================================

    public function indexOrders(Request $request): JsonResponse
    {
        $orders = $this->wholesaleService->listOrders(
            $request->only(['search', 'status', 'payment_status', 'company_id', 'per_page'])
        );

        return $this->apiResponse(true, 'Wholesale orders fetched successfully.', $orders);
    }

    public function showOrder(int $id): JsonResponse
    {
        $order = $this->wholesaleService->getOrder($id);

        return $this->apiResponse(true, 'Wholesale order details fetched successfully.', $order);
    }

    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'payment_status' => ['sometimes', 'required', 'in:pending,paid,refunded,failed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $order = $this->wholesaleService->updateOrderStatus($id, $validated);

            return $this->apiResponse(true, 'Wholesale order updated successfully.', $order);
        } catch (ValidationException $e) {
            return $this->apiResponse(false, $e->getMessage(), ['errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(false, 'Failed to update wholesale order: ' . $e->getMessage(), null, 500);
        }
    }
}

