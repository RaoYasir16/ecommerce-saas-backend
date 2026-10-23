<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SuperAdmin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of all users across all companies.
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->getAllUsers(
            $request->only(['search', 'company_id', 'role', 'per_page'])
        );

        return $this->apiResponse(
            true,
            'Users fetched successfully.',
            $users
        );
    }

    /**
     * Display users for a specific company.
     */
    public function companyUsers(Request $request, Company|int|string $company): JsonResponse
    {
        $users = $this->userService->getCompanyUsers(
            $company,
            $request->only(['search', 'role', 'per_page'])
        );

        return $this->apiResponse(
            true,
            'Company users fetched successfully.',
            $users
        );
    }
}
