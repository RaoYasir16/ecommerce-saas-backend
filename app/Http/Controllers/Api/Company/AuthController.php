<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyLoginRequest;
use App\Http\Requests\CompanyRegisterRequest;
use App\Http\Requests\CompanyResendOtpRequest;
use App\Http\Requests\CompanyVerifyOtpRequest;
use App\Services\Company\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            'Shop registered successfully! Verification OTP sent to your email.',
            $result,
            201
        );
    }

    /**
     * Verify company registration OTP.
     */
    public function verifyOtp(CompanyVerifyOtpRequest $request)
    {
        $result = $this->authService->verifyOtp(
            $request->validated()
        );

        return $this->apiResponse(
            true,
            'Email verified successfully! Store account activated.',
            $result,
            200
        );
    }

    /**
     * Resend verification OTP.
     */
    public function resendOtp(CompanyResendOtpRequest $request)
    {
        $this->authService->resendOtp(
            $request->email,
            $request->type ?? 'register'
        );

        return $this->apiResponse(
            true,
            'A new verification OTP has been sent to your email.',
            null,
            200
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

    /**
     * Forgot password - Send OTP to email.
     */
    public function forgotPasswordSendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $result = $this->authService->forgotPasswordSendOtp($request->email);

        return $this->apiResponse(
            true,
            'Password reset verification code sent to your email.',
            $result,
            200
        );
    }

    /**
     * Forgot password - Verify OTP.
     */
    public function forgotPasswordVerifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $this->authService->forgotPasswordVerifyOtp($request->email, $request->otp);

        return $this->apiResponse(
            true,
            'Verification code is valid.',
            null,
            200
        );
    }

    /**
     * Forgot password - Reset password.
     */
    public function forgotPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email'                 => ['required', 'string', 'email'],
            'otp'                   => ['required', 'string', 'size:6'],
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $result = $this->authService->forgotPasswordReset($validated);

        return $this->apiResponse(
            true,
            'Your password has been reset successfully. Please login with your new password.',
            $result,
            200
        );
    }
}
