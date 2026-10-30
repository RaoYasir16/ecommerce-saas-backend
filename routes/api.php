<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/test', function () {
    return response()->json([
        'status' => true,
        'message' => 'API is working',
    ]);
});

// Serve storage files when requested via /api/storage/{path}
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if (!$disk->exists($path)) {
        return response()->json([
            'status' => false,
            'message' => 'File not found.',
            'data' => null,
        ], 404);
    }
    return response()->file($disk->path($path));
})->where('path', '.*');

require __DIR__ . '/api/company.php';
require __DIR__ . '/api/customer.php';
require __DIR__ . '/api/SuperAdmin.php';

