
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use App\Http\Controllers\Api\SuperAdmin\OrderController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;

Route::prefix('super-admin')->group(function () {

    // Public routes
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/companies/{company}', [CompanyController::class, 'show']);
        Route::get('/companies/{company}/users', [UserController::class, 'companyUsers']);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/orders', [OrderController::class, 'index']);

        Route::apiResource('/subscription-plans',SubscriptionPlanController::class);
    });
});
