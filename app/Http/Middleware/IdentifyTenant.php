<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Reserved subdomains that belong to the platform infrastructure, not tenants.
     */
    protected const RESERVED_SUBDOMAINS = [
        'admin',
        'api',
        'www',
        'mail',
        'dashboard',
        'root',
        'support',
        'superadmin',
        'merchant',
    ];

    /**
     * Extract tenant subdomain from request headers, host, or query parameters.
     */
    public static function extractSubdomain(Request $request): ?string
    {
        // 1. Explicit Header (API requests from SPA, mobile, or curl)
        $headerSubdomain = $request->header('X-Store-Subdomain') ?? $request->header('X-Tenant');
        if (!empty($headerSubdomain)) {
            $clean = strtolower(trim((string) $headerSubdomain));
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $clean)) {
                return $clean;
            }
        }

        // 2. Extract from Host header (e.g. noormega.noorwear.pk, noormega.localhost)
        $host = strtolower($request->getHost());

        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        $platformDomain = strtolower((string) config('services.hostinger.domain', 'noorwear.pk'));
        $subdomainCandidate = null;

        if (!empty($platformDomain) && str_ends_with($host, '.' . $platformDomain)) {
            $prefix = substr($host, 0, -strlen('.' . $platformDomain));
            $parts = explode('.', $prefix);
            $subdomainCandidate = end($parts);
        } else {
            // Local development domains
            $localRoots = ['localhost', 'test', 'local'];
            foreach ($localRoots as $root) {
                if (str_ends_with($host, '.' . $root)) {
                    $prefix = substr($host, 0, -strlen('.' . $root));
                    $parts = explode('.', $prefix);
                    $subdomainCandidate = end($parts);
                    break;
                }
            }

            // IP wildcard services like 127.0.0.1.nip.io
            if (!$subdomainCandidate && str_contains($host, '.nip.io')) {
                $parts = explode('.', $host);
                if (count($parts) > 4) {
                    $subdomainCandidate = $parts[0];
                }
            }
        }

        if ($subdomainCandidate !== null) {
            $clean = strtolower(trim($subdomainCandidate));
            if (!in_array($clean, self::RESERVED_SUBDOMAINS, true) && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $clean)) {
                return $clean;
            }
        }

        // 3. Fallback query parameter for dev/testing
        $queryParam = $request->query('subdomain') ?? $request->query('store');
        if (!empty($queryParam)) {
            $clean = strtolower(trim((string) $queryParam));
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $clean)) {
                return $clean;
            }
        }

        return null;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = self::extractSubdomain($request);

        if ($subdomain === null) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'A valid store subdomain is required.',
                'data' => null,
            ], 400);
        }

        $company = Company::where('subdomain', $subdomain)->first();

        if (!$company) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Store not found.',
                'data' => null,
            ], 404);
        }

        // Enforce company active/trial business rules
        if (!in_array($company->status, ['trial', 'active'], true)) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Store is currently unavailable or inactive.',
                'data' => null,
            ], 403);
        }

        // Bind the identified tenant into Laravel's service container
        app()->instance('currentCompany', $company);

        // Also bind to request attributes for backward compatibility
        $request->attributes->set('storeCompany', $company);
        $request->attributes->set('currentCompany', $company);

        return $next($request);
    }
}
