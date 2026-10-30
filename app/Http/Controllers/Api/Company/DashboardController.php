<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Company\DashboardServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardServices $dashboardService
    ) {}

    /**
     * Get real-time dashboard analytics for authenticated merchant store.
     */
    public function analytics(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->apiResponse(false, 'Merchant company not found.', null, 404);
        }

        $analytics = $this->dashboardService->getAnalytics($company);

        return $this->apiResponse(true, 'Dashboard analytics fetched successfully.', $analytics);
    }
}
