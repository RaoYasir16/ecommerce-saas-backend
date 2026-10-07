<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function apiResponse(
        bool $status,
        string $message,
        mixed $data = null,
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}