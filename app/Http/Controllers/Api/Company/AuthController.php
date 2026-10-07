<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyLoginRequest;
use App\Http\Requests\CompanyRegisterRequest;
use App\Services\Company\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Check subdomain availability.
     */
    public function checkSubdomain(Request $request)
    {
        $request->validate([
            'subdomain' => ['required', 'string'],
        ]);

        $result = $this->authService->checkSubdomain(
            $request->subdomain
        );

        return $this->apiResponse(
            $result['available'],
            $result['message'],
            [
                'subdomain' => $result['subdomain'],
                'available' => $result['available'],
            ],
            200
        );
    }

    /**
     * Company registration.
     */
    public function register(CompanyRegisterRequest $request)
    {
        $result = $this->authService->register(
            $request->validated()
        );

        return $this->apiResponse(
            true,
            'Shop registered successfully! 1-month free trial activated.',
            $result,
            201
        );
    }

    /**
     * Company login.
     */
    public function login(CompanyLoginRequest $request)
    {
        $result = $this->authService->login(
            $request->validated()
        );

        return $this->apiResponse(
            true,
            'Login successful.',
            $result,
            200
        );
    }
    
    /**
     * Company logout.
     */
    public function logout(Request $request)
    {
        $this->authService->logout(
            $request->user()
        );

        return $this->apiResponse(
            true,
            'Logged out successfully.',
            null,
            200
        );
    }
}
