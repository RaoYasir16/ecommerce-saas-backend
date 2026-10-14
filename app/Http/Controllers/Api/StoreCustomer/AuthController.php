<?php

namespace App\Http\Controllers\Api\StoreCustomer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\LoginRequest;
use App\Http\Requests\Customer\Auth\RegisterRequest;
use App\Http\Requests\Customer\Auth\ResendOtpRequest;
use App\Http\Requests\Customer\Auth\VerifyOtpRequest;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private CustomerAuthService $customerAuthService
    ) {}

    // Register Otp
    public function register(RegisterRequest $request)
    {
        $company = $request->attributes->get('storeCompany');

        $data = $this->customerAuthService->register(
            $request->validated(),
            $company
        );

        return $this->apiResponse(
            true,
            'Registration successful. OTP sent to your email.',
            $data
        );
    }

    // Resend Otp
    public function resendOtp(ResendOtpRequest $request)
    {
        $this->customerAuthService->resendOtp($request->email, $request->type);

        return response()->json([
            'status' => true,
            'message' => 'A new OTP has been sent to your email.',
        ]);
    }


    // Verify Otp
    public function verifyOtp(VerifyOtpRequest $request)
    {
        $company = $request->attributes->get('storeCompany');

        $data = $this->customerAuthService->verifyOtp(
            $request->validated(),
            $company
        );

        return $this->apiResponse(
            true,
            'Email verified successfully.',
            $data
        );
    }

    // Login 
    public function login(LoginRequest $request)
    {
        $company = $request->attributes->get('storeCompany');

        $data = $this->customerAuthService->login(
            $request->validated(),
            $company
        );

        return $this->apiResponse(
            true,
            'Login successful.',
            $data
        );
    }
}
