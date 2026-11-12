
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
use App\Http\Controllers\Api\SuperAdmin\PlatformWalletController;
use App\Http\Controllers\Api\SuperAdmin\ActivityLogController;
use App\Http\Controllers\Api\SuperAdmin\WholesaleController;

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

        // Platform Wallet Withdrawals Approval & Fee Collection
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', [CompanyWalletController::class, 'withdrawalRequests']);
            Route::post('/{id}/approve', [CompanyWalletController::class, 'approveWithdrawal']);
            Route::post('/{id}/reject', [CompanyWalletController::class, 'rejectWithdrawal']);
        });

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/{id}/status', [UserController::class, 'updateStatus']);
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

        // Platform Treasury & Master Wallet
        Route::prefix('platform-wallet')->group(function () {
            Route::get('/', [PlatformWalletController::class, 'getWallet']);
            Route::post('/currency', [PlatformWalletController::class, 'updateCurrency']);
            Route::post('/adjust', [PlatformWalletController::class, 'adjustBalance']);
            Route::get('/transactions', [PlatformWalletController::class, 'transactions']);
        });

        Route::get('/referrals', [PlatformSettingController::class, 'referralOverview']);

        // Platform Activity Logs
        Route::prefix('activities')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index']);
            Route::get('/{id}', [ActivityLogController::class, 'show']);
        });

        // B2B Wholesale Hub Management (Categories, Products, Orders)
        Route::prefix('wholesale')->group(function () {
            Route::prefix('categories')->group(function () {
                Route::get('/', [WholesaleController::class, 'indexCategories']);
                Route::post('/', [WholesaleController::class, 'storeCategory']);
                Route::post('/{id}', [WholesaleController::class, 'updateCategory']);
                Route::put('/{id}', [WholesaleController::class, 'updateCategory']);
                Route::delete('/{id}', [WholesaleController::class, 'destroyCategory']);
            });

            Route::prefix('products')->group(function () {
                Route::get('/', [WholesaleController::class, 'indexProducts']);
                Route::post('/', [WholesaleController::class, 'storeProduct']);
                Route::get('/{id}', [WholesaleController::class, 'showProduct']);
                Route::post('/{id}', [WholesaleController::class, 'updateProduct']);
                Route::put('/{id}', [WholesaleController::class, 'updateProduct']);
                Route::delete('/{id}', [WholesaleController::class, 'destroyProduct']);
            });

            Route::prefix('orders')->group(function () {
                Route::get('/', [WholesaleController::class, 'indexOrders']);
                Route::get('/{id}', [WholesaleController::class, 'showOrder']);
                Route::post('/{id}/status', [WholesaleController::class, 'updateOrderStatus']);
                Route::put('/{id}/status', [WholesaleController::class, 'updateOrderStatus']);
            });
        });
    });
});
