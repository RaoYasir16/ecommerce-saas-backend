<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('user')->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        // User table ki role ID se roles table ka role find karein
        $role = DB::table('roles')
            ->where('id', $user->role)
            ->first();

        if (!$role || strtolower($role->name) !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Only company administrators can access this resource.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}