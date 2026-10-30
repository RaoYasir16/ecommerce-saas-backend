<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = strtolower(trim(
            (string) $request->header('X-Store-Subdomain')
        ));

        if (
            $subdomain === '' ||
            !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $subdomain)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'A valid store subdomain is required.',
                'data' => null,
            ], 400);
        }

        $company = Company::query()
            ->where('subdomain', $subdomain)
            ->whereIn('status', ['trial', 'active'])
            ->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found or inactive.',
                'data' => null,
            ], 404);
        }

        $request->attributes->set('storeCompany', $company);

        return $next($request);
    }
}