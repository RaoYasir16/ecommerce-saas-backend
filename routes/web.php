<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Direct storage fallback route
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404, 'File not found.');
    }
    return response()->file($disk->path($path));
})->where('path', '.*');

