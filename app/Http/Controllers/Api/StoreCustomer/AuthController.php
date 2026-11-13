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

    // Customer Forgot Password - Send OTP
    public function forgotPasswordSendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $company = $request->attributes->get('storeCompany');

        $result = $this->customerAuthService->forgotPasswordSendOtp(
            $request->email,
            $company
        );

        return $this->apiResponse(
            true,
            'Password reset verification code sent to your email.',
            $result,
            200
        );
    }

    // Customer Forgot Password - Verify OTP
    public function forgotPasswordVerifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $company = $request->attributes->get('storeCompany');

        $this->customerAuthService->forgotPasswordVerifyOtp(
            $request->email,
            $request->otp,
            $company
        );

        return $this->apiResponse(
            true,
            'Verification code is valid.',
            null,
            200
        );
    }

    // Customer Forgot Password - Reset Password
    public function forgotPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email'                 => ['required', 'string', 'email'],
            'otp'                   => ['required', 'string', 'size:6'],
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $company = $request->attributes->get('storeCompany');

        $result = $this->customerAuthService->forgotPasswordReset(
            $validated,
            $company
        );

        return $this->apiResponse(
            true,
            'Your password has been reset successfully. Please login with your new password.',
            $result,
            200
        );
    }

    // Customer Authenticated - Send Password Reset OTP
    public function sendPasswordResetOtp(Request $request)
    {
        $company = $request->attributes->get('storeCompany');

        $result = $this->customerAuthService->sendPasswordResetOtp(
            $request->user(),
            $company
        );

        return $this->apiResponse(
            true,
            "Verification code sent to {$result['masked_email']}.",
            $result,
            200
        );
    }

    // Customer Authenticated - Reset Password
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $this->customerAuthService->resetPassword(
            $request->user(),
            $validated
        );

        return $this->apiResponse(
            true,
            'Password has been updated successfully.',
            null,
            200
        );
    }
}
