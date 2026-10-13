<?php

use App\Http\Controllers\Api\StoreCustomer\StoreController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveStore;

Route::middleware(ResolveStore::class)->prefix('store')->group(function () {
    Route::prefix('customer')->group(function () {
        // Company Authentication
        // Route::post('/check-subdomain', [AuthController::class, 'checkSubdomain']);
        // Route::post('/register', [AuthController::class, 'register']);
        // Route::post('/login', [AuthController::class, 'login']);


        //Authenticated Company Routes
        // Route::middleware('auth:user')->group(function () {
            Route::get('/show-store', [StoreController::class, 'show']);
            Route::get('/categories', [StoreController::class, 'categories']);
            Route::get('/products', [StoreController::class, 'products']);
            Route::get('/products/{slug}', [StoreController::class, 'product']);
        });
    // });
});
