<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SuperAdmin\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        protected CompanyService $companyService
    ) {}

    /**
     * Display a listing of all companies.
     */
    public function index(Request $request): JsonResponse
    {
        $companies = $this->companyService->getAllCompanies(
            $request->only(['search', 'status', 'per_page'])
        );

        return $this->apiResponse(
            true,
            'Companies fetched successfully.',
            $companies
        );
    }

    /**
     * Display the specified company details.
     */
    public function show(Company|int|string $company): JsonResponse
    {
        $companyDetails = $this->companyService->getCompanyDetails($company);

        return $this->apiResponse(
            true,
            'Company details fetched successfully.',
            $companyDetails
        );
    }
}
