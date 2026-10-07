<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn(Request $request) => null);
    })

    ->withExceptions(function (Exceptions $exceptions) {

        // Validation
        $exceptions->render(function (
            ValidationException $e,
            Request $request
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'data' => $e->errors(),
            ], 422);
        });

        // Authentication
        $exceptions->render(function (
            AuthenticationException $e,
            Request $request
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        });

        // Model Not Found
        $exceptions->render(function (
            ModelNotFoundException $e,
            Request $request
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Resource not found.',
                'data' => null,
            ], 404);
        });

        // Route Not Found
        $exceptions->render(function (
            NotFoundHttpException $e,
            Request $request
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Route not found.',
                'data' => null,
            ], 404);
        });
    })

    ->create();
