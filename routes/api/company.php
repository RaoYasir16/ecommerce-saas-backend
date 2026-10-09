<?php

use App\Http\Controllers\Api\Company\AuthController;
use App\Http\Controllers\Api\Company\ProductController;
use App\Http\Controllers\Api\Company\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('company')->group(function () {
    // Company Authentication
    Route::post('/check-subdomain', [AuthController::class, 'checkSubdomain']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);


    //Authenticated Company Routes
    Route::middleware('auth:user')->group(function () {
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

            Route::get('/',[ProductController::class,'']);
            Route::post('/create',[ProductController::class,'']);
            Route::get('/single/{id}',[ProductController::class,'']);
            Route::post('/update/{id}',[ProductController::class,'']);
            Route::delete('/delete/{id}',[ProductController::class,'']);
        });
    });
});
