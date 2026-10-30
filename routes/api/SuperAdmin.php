
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController;
use App\Http\Controllers\Api\SuperAdmin\CompanyWalletController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use App\Http\Controllers\Api\SuperAdmin\OrderController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionPlanController;
use App\Http\Controllers\Api\SuperAdmin\PlatformSettingController;

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

        // Company Wallet Platform Controls
        Route::prefix('companies/{company}/wallet')->group(function () {
            Route::get('/', [CompanyWalletController::class, 'show']);
            Route::post('/status', [CompanyWalletController::class, 'updateStatus']);
            Route::post('/adjust', [CompanyWalletController::class, 'adjustBalance']);
            Route::get('/transactions', [CompanyWalletController::class, 'transactions']);
        });

        // Platform Wallet Deposits Approval & Management
        Route::prefix('deposits')->group(function () {
            Route::get('/', [CompanyWalletController::class, 'depositRequests']);
            Route::post('/{id}/approve', [CompanyWalletController::class, 'approveDeposit']);
            Route::post('/{id}/reject', [CompanyWalletController::class, 'rejectDeposit']);
        });

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/orders', [OrderController::class, 'index']);

        Route::prefix('/subscription-plans')->group(function () {

            Route::get('/',[SubscriptionPlanController::class,'index']);
            Route::post('/create',[SubscriptionPlanController::class,'store']);
            Route::get('/{id}',[SubscriptionPlanController::class,'show']);
            Route::put('/update/{id}',[SubscriptionPlanController::class,'update']);
            Route::delete('/delete/{id}',[SubscriptionPlanController::class,'destroy']);
        });

        // Platform Settings & Referral Commission Configuration
        Route::prefix('platform-settings')->group(function () {
            Route::get('/', [PlatformSettingController::class, 'getSettings']);
            Route::post('/', [PlatformSettingController::class, 'updateSettings']);
            Route::put('/', [PlatformSettingController::class, 'updateSettings']);
        });

        Route::get('/referrals', [PlatformSettingController::class, 'referralOverview']);
    });
});
