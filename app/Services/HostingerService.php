<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HostingerService
{
    protected ?string $apiToken;
    protected ?string $username;
    protected string $domain;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct(
        ?string $apiToken = null,
        ?string $username = null,
        ?string $domain = null,
        string $baseUrl = 'https://developers.hostinger.com/api/hosting/v1',
        int $timeout = 30
    ) {
        $this->apiToken = $apiToken ?? config('services.hostinger.api_token');
        $this->username = $username ?? config('services.hostinger.username');
        $this->domain = $domain ?? config('services.hostinger.domain', 'noorwear.pk');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Check if Hostinger configuration is set.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->username) && !empty($this->domain);
    }

    /**
     * Create a subdomain on Hostinger for the website.
     *
     * @param string $subdomain The subdomain prefix (e.g. 'noormega')
     * @return array Structured response ['success' => bool, 'already_exists' => bool, 'status' => int, 'message' => string, 'data' => mixed]
     */
    public function createSubdomain(string $subdomain): array
    {
        $cleanSubdomain = Str::slug($subdomain);

        if (!$this->isConfigured()) {
            Log::warning('Hostinger API credentials are not fully configured. Subdomain creation skipped/failed.', [
                'subdomain' => $cleanSubdomain,
                'has_token' => !empty($this->apiToken),
                'has_username' => !empty($this->username),
                'domain' => $this->domain,
            ]);

            return [
                'success' => false,
                'already_exists' => false,
                'status' => 500,
                'message' => 'Hostinger API credentials are not configured.',
                'data' => null,
            ];
        }

        $endpoint = "{$this->baseUrl}/accounts/{$this->username}/websites/{$this->domain}/subdomains";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($endpoint, [
                'subdomain' => $cleanSubdomain,
                'isUsingPublicDirectory' => true,
            ]);

            $statusCode = $response->status();
            $responseData = $response->json();

            // HTTP 200 or 201 indicates success
            if ($response->successful()) {
                Log::info('Hostinger subdomain created successfully.', [
                    'subdomain' => $cleanSubdomain,
                    'domain' => $this->domain,
                    'status' => $statusCode,
                ]);

                return [
                    'success' => true,
                    'already_exists' => false,
                    'status' => $statusCode,
                    'message' => 'Subdomain created successfully on Hostinger.',
                    'data' => $responseData,
                ];
            }

            // HTTP 409 Conflict or 422 where error indicates subdomain already exists
            $responseBodyStr = strtolower($response->body());
            $isAlreadyExists = $statusCode === 409 ||
                str_contains($responseBodyStr, 'already exists') ||
                str_contains($responseBodyStr, 'already taken') ||
                str_contains($responseBodyStr, 'already in use');

            if ($isAlreadyExists) {
                Log::info('Hostinger subdomain already exists on account.', [
                    'subdomain' => $cleanSubdomain,
                    'domain' => $this->domain,
                    'status' => $statusCode,
                ]);

                return [
                    'success' => true,
                    'already_exists' => true,
                    'status' => $statusCode,
                    'message' => 'Subdomain already exists on Hostinger.',
                    'data' => $responseData,
                ];
            }

            // Other HTTP client / server errors (4xx, 5xx)
            // Log failure WITHOUT exposing the API token or credentials
            Log::error('Hostinger API subdomain creation returned an error response.', [
                'subdomain' => $cleanSubdomain,
                'domain' => $this->domain,
                'status' => $statusCode,
                'error' => $responseData['message'] ?? $response->body(),
            ]);

            return [
                'success' => false,
                'already_exists' => false,
                'status' => $statusCode,
                'message' => $responseData['message'] ?? ('Hostinger API request failed with status ' . $statusCode),
                'data' => $responseData,
            ];

        } catch (ConnectionException $e) {
            Log::error('Connection to Hostinger API timed out or failed.', [
                'subdomain' => $cleanSubdomain,
                'domain' => $this->domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'already_exists' => false,
                'status' => 504,
                'message' => 'Connection to Hostinger API timed out.',
                'data' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected exception during Hostinger subdomain creation.', [
                'subdomain' => $cleanSubdomain,
                'domain' => $this->domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'already_exists' => false,
                'status' => 500,
                'message' => 'Unexpected error communicating with Hostinger API.',
                'data' => null,
            ];
        }
    }

    /**
     * Fetch existing subdomains from Hostinger.
     */
    public function getSubdomains(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 500,
                'message' => 'Hostinger API credentials are not configured.',
                'data' => [],
            ];
        }

        $endpoint = "{$this->baseUrl}/accounts/{$this->username}/websites/{$this->domain}/subdomains";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->get($endpoint);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'message' => 'Subdomains fetched successfully.',
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'message' => $response->json('message') ?? 'Failed to fetch subdomains.',
                'data' => [],
            ];
        } catch (Throwable $e) {
            Log::error('Failed to get subdomains from Hostinger.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Get the configured base domain.
     */
    public function getBaseDomain(): string
    {
        return $this->domain;
    }
}
