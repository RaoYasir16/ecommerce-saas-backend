<?php

namespace App\Http\Controllers\Api\StoreCustomer;

use App\Http\Controllers\Controller;
use App\Services\StoreCustomer\StoreService;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    public function show(Request $request)
    {
        $company = $request->attributes->get('storeCompany');

        return $this->apiResponse(true,'Store fetched successfully.', $this->storeService->getStore($company));
    }

    public function products(Request $request)
    {
        $company = $request->attributes->get('storeCompany');

        $products = $this->storeService->getProducts(
            $company,
            $request->only(['search', 'category_id', 'per_page'])
        );

        return $this->apiResponse(true,'Products fetched successfully',$products,);
    }

    public function product(Request $request, string $slug)
    {
        $company = $request->attributes->get('storeCompany');

        $product = $this->storeService->getProduct($company, $slug);

        return $this->apiResponse(true,'Products fatched Successfully',$product,);

    }

    public function categories(Request $request)
    {
        $company = $request->attributes->get('storeCompany');

        return $this->apiResponse(true,'Categories Fatch Successfully',$this->storeService->getCategories($company));
    }
}
