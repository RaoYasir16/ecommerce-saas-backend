<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCategoryRequest;
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
}
