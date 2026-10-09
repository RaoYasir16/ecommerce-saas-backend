<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCategoryRequest;
use App\Http\Requests\StoreProductRequest;
use App\Services\Company\ProductServices;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductServices $productService
    ) {}

    /**
     * Add new Category
     */
    public function addCategory(ProductCategoryRequest $request)
    {
        $company = auth()->user()->company;

        $category = $this->productService->addCategory(
            $company,
            $request->validated()
        );

        return $this->apiResponse(
            true,
            'Category created successfully.',
            $category
        );
    }

    /**
     * Get all categories
     */
    public function getAllCategory()
    {
        $company = auth()->user()->company;

        $categories = $this->productService->getAllCategories($company);

        return $this->apiResponse(
            true,
            'Categories fetched successfully.',
            $categories
        );
    }

    /**
     * Delete Category
     */
    public function deleteCategory(int $id)
    {
        $company = auth()->user()->company;

        $this->productService->deleteCategory(
            $company,
            $id
        );

        return $this->apiResponse(
            true,
            'Category deleted successfully.'
        );
    }



    //=====================> Products Code <==================================//

    public function index(Request $request)
    {
        $company = auth()->user()->company;

        $products = $this->productService->getAllProducts(
            $company,
            $request->only(['search', 'category_id', 'is_available', 'per_page'])
        );

        return $this->apiResponse(
            true,
            'Products fetched successfully.',
            $products
        );
    }

    public function store(StoreProductRequest $request)
    {
        $company = auth()->user()->company;

        try {
            $product = $this->productService->createProduct(
                $company,
                $request->validated()
            );

            return $this->apiResponse(
                true,
                'Product created successfully.',
                $product
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Unable to create product.',
                null
            );
        }
    }

    public function show(int $id)
    {
        $company = auth()->user()->company;

        $product = $this->productService->getProductById($company, $id);

        return $this->apiResponse(
            true,
            'Product fetched successfully.',
            $product
        );
    }

    public function update(UpdateProductRequest $request, int $id)
    {
        $company = auth()->user()->company;

        try {
            $product = $this->productService->updateProduct(
                $company,
                $id,
                $request->validated()
            );

            return $this->apiResponse(
                true,
                'Product updated successfully.',
                $product
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Unable to update product.',
                null
            );
        }
    }

    public function destroy(int $id)
    {
        $company = auth()->user()->company;

        try {
            $this->productService->deleteProduct($company, $id);

            return $this->apiResponse(
                true,
                'Product deleted successfully.',
                null
            );
        } catch (Throwable $e) {
            report($e);

            return $this->apiResponse(
                false,
                'Unable to delete product.',
                null
            );
        }
    }
}
