<?php

use App\Http\Controllers\Api\StoreCustomer\AuthController;
use App\Http\Controllers\Api\StoreCustomer\CustomerStoreController;
use App\Http\Controllers\Api\StoreCustomer\StoreController;
use App\Http\Middleware\ResolveStore;
use Illuminate\Support\Facades\Route;

Route::middleware(ResolveStore::class)->group(function () {

    Route::prefix('customer')->group(function () {
        // Auth Routes
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/login', [AuthController::class, 'login']);

        // Public Catalog Routes
        Route::get('/show-store', [StoreController::class, 'show']);
        Route::get('/categories', [StoreController::class, 'categories']);
        Route::get('/products', [StoreController::class, 'products']);
        Route::get('/products/{slug}', [StoreController::class, 'product']);
        Route::get('/products/{id}/reviews', [CustomerStoreController::class, 'getProductReviews']);

        Route::middleware('auth:user')->group(function () {
            // Favorite / Wishlist Routes
            Route::prefix('favorite')->group(function () {
                Route::post('/add', [CustomerStoreController::class, 'addFavorite']);
                Route::get('/get-all', [CustomerStoreController::class, 'getFavorites']);
                Route::post('/remove/{id?}', [CustomerStoreController::class, 'removeFavorite']);
                Route::delete('/remove/{id?}', [CustomerStoreController::class, 'removeFavorite']);
            });

            // Cart Routes
            Route::prefix('add-to-cart')->group(function () {
                Route::post('/add', [CustomerStoreController::class, 'addToCart']);
                Route::get('/get-all', [CustomerStoreController::class, 'getCart']);
                Route::post('/remove/{id?}', [CustomerStoreController::class, 'removeFromCart']);
                Route::delete('/remove/{id?}', [CustomerStoreController::class, 'removeFromCart']);
                Route::post('/update', [CustomerStoreController::class, 'updateCartQuantity']);
                Route::delete('/clear', [CustomerStoreController::class, 'clearCart']);
            });

            // Order Routes
            Route::prefix('order')->group(function () {
                Route::post('/place', [CustomerStoreController::class, 'placeOrder']);
                Route::get('/get-all', [CustomerStoreController::class, 'getOrders']);
                Route::get('/single/{id}', [CustomerStoreController::class, 'showOrder']);
                Route::post('/cancel/{id?}', [CustomerStoreController::class, 'cancelOrder']);
            });

            // Review Routes
            Route::prefix('review')->group(function () {
                Route::post('/add', [CustomerStoreController::class, 'addReview']);
                Route::delete('/delete/{id?}', [CustomerStoreController::class, 'deleteReview']);
                Route::post('/delete/{id?}', [CustomerStoreController::class, 'deleteReview']);
            });
        });
    });
});
