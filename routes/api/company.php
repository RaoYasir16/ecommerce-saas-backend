<?php

use App\Http\Controllers\Api\Company\AuthController;
use App\Http\Controllers\Api\Company\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('company')->group(function () {
    // Company Authentication
    Route::post('/check-subdomain', [AuthController::class, 'checkSubdomain']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);


    //Authenticated Company Routes
    Route::middleware('auth:user')->group(function () {
        Route::prefix('profile')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);

            Route::get('/', [ProfileController::class, 'profile']);
            Route::post('/update', [ProfileController::class,'updateProfile']);

            Route::get('/store-setting',[ProfileController::class,'getStoreSetting']);
            Route::post('/store-setting/update',[ProfileController::class,'updateStoreSetting']);
        });
    });
});
