<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\HostingerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CreateCompanySubdomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Exponential backoff delays in seconds between retries.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $companyId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(HostingerService $hostingerService): void
    {
        $company = Company::find($this->companyId);

        if (!$company) {
            Log::warning('CreateCompanySubdomainJob: Company not found.', [
                'company_id' => $this->companyId,
            ]);
            return;
        }

        // If subdomain is already active, no further action required
        if ($company->domain_status === 'active') {
            Log::info("CreateCompanySubdomainJob: Domain is already active for company {$company->id}.", [
                'domain' => $company->domain,
            ]);
            return;
        }

        // If Hostinger credentials are not configured (e.g. local environment or testing), skip gracefully
        if (!$hostingerService->isConfigured()) {
            Log::warning("CreateCompanySubdomainJob: Hostinger API credentials are not configured. Subdomain creation skipped.", [
                'company_id' => $company->id,
                'subdomain' => $company->subdomain,
            ]);
            return;
        }

        Log::info("CreateCompanySubdomainJob: Attempting to create Hostinger subdomain for company {$company->id}.", [
            'subdomain' => $company->subdomain,
            'attempt' => $this->attempts(),
        ]);

        $result = $hostingerService->createSubdomain($company->subdomain);

        if ($result['success']) {
            $company->update([
                'domain_status' => 'active',
            ]);

            Log::info("CreateCompanySubdomainJob: Hostinger subdomain successfully activated for company {$company->id}.", [
                'subdomain' => $company->subdomain,
                'domain' => $company->domain,
                'already_existed' => $result['already_exists'] ?? false,
            ]);

            return;
        }

        // On failure, log the error and throw an exception to let Laravel retry according to backoff
        $errorMessage = $result['message'] ?? 'Unknown error from Hostinger API';

        Log::error("CreateCompanySubdomainJob: Failed to create subdomain on attempt {$this->attempts()} for company {$company->id}.", [
            'subdomain' => $company->subdomain,
            'status' => $result['status'] ?? null,
            'error' => $errorMessage,
        ]);

        throw new RuntimeException("Hostinger subdomain creation failed: {$errorMessage}");
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $company = Company::find($this->companyId);

        if ($company && $company->domain_status !== 'active') {
            $company->update([
                'domain_status' => 'failed',
            ]);
        }

        Log::critical("CreateCompanySubdomainJob: All retry attempts exhausted for company {$this->companyId}. Domain status marked as 'failed'.", [
            'company_id' => $this->companyId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
