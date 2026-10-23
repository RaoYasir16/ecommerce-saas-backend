<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AuthRequest;
use App\Services\SuperAdmin\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function login(AuthRequest $request)
    {
        $result = $this->authService->login($request->validated());

        return $this->apiResponse(true,'Admin Logged in successfully',$result,200);

    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->apiResponse(true,'Admin logged out successfully',);
    }
}
