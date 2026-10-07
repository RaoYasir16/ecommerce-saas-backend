<?php

use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'status' => true,
        'message' => 'API is working',
    ]);
});


require __DIR__ . '/api/company.php';
