<?php

use App\Http\Controllers\Api\Company\AuthController;
use App\Http\Controllers\Api\Company\DashboardController;
use App\Http\Controllers\Api\Company\OrderController;
use App\Http\Controllers\Api\Company\ProductController;
use App\Http\Controllers\Api\Company\ProfileController;
use App\Http\Controllers\Api\Company\WalletController;
use App\Http\Controllers\Api\Company\SubscriptionController;
use App\Http\Controllers\Api\Company\ReferralController;
use App\Http\Middleware\EnsureCompanyAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('company')->group(function () {
    // Company Authentication
    Route::post('/check-subdomain', [AuthController::class, 'checkSubdomain']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/login', [AuthController::class, 'login']);


    //Authenticated Company Routes
    Route::middleware('auth:user', EnsureCompanyAdmin::class)->group(function () {
        // Dashboard Analytics
        Route::prefix('dashboard')->group(function () {
            Route::get('/analytics', [DashboardController::class, 'analytics']);
        });

        // Orders Management
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/single/{id}', [OrderController::class, 'show']);
            Route::post('/{id}/status', [OrderController::class, 'updateStatus']);
        });

        // Profile and store setting routes
        Route::prefix('profile')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);

            Route::get('/', [ProfileController::class, 'profile']);
            Route::post('/update', [ProfileController::class,'updateProfile']);

            Route::get('/store-setting',[ProfileController::class,'getStoreSetting']);
            Route::post('/store-setting/update',[ProfileController::class,'updateStoreSetting']);
        });

        // products and category routes
        Route::prefix('products')->group(function () {
            Route::get('/category',[ProductController::class,'getAllCategory']);
            Route::post('/category/add',[ProductController::class,'addCategory']);
            Route::delete('/category/delete/{id}',[ProductController::class,'deleteCategory']);

            Route::get('/',[ProductController::class,'index']);
            Route::post('/create',[ProductController::class,'store']);
            Route::get('/single/{id}',[ProductController::class,'show']);
            Route::post('/update/{id}',[ProductController::class,'update']);
            Route::delete('/delete/{id}',[ProductController::class,'destroy']);
        });

        // Wallet Management
        Route::prefix('wallet')->group(function () {
            Route::get('/balance', [WalletController::class, 'getBalance']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
            Route::get('/payment-details', [WalletController::class, 'paymentDetails']);
            Route::get('/deposits', [WalletController::class, 'deposits']);
            Route::post('/deposits', [WalletController::class, 'createDeposit']);
        });

        // Subscription Management
        Route::prefix('subscription')->group(function () {
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::get('/current', [SubscriptionController::class, 'current']);
            Route::get('/', [SubscriptionController::class, 'current']);
            Route::post('/purchase', [SubscriptionController::class, 'purchase']);
            Route::post('/upgrade', [SubscriptionController::class, 'purchase']);
            Route::get('/invoices', [SubscriptionController::class, 'invoices']);
        });

        // Referral System Management
        Route::prefix('referrals')->group(function () {
            Route::get('/', [ReferralController::class, 'index']);
        });
    });
});
