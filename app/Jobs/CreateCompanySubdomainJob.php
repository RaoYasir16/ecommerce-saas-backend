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

        // If Hostinger credentials are not configured (e.g. local environment or wildcard subdomain setup),
        // activate domain status automatically since wildcard DNS handles routing.
        if (!$hostingerService->isConfigured()) {
            Log::info("CreateCompanySubdomainJob: Hostinger API credentials are not configured. Subdomain activated via wildcard routing.", [
                'company_id' => $company->id,
                'subdomain' => $company->subdomain,
            ]);
            $company->update([
                'domain_status' => 'active',
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

            // Link single storefront codebase to the newly created Hostinger subdomain folder
            $this->linkStorefrontFiles($company->subdomain);

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
     * Copy multi-tenant storefront files into the Hostinger subdomain directory.
     */
    protected function linkStorefrontFiles(string $subdomain): void
    {
        try {
            $clean = \Illuminate\Support\Str::slug($subdomain);
            $mainStoreDir = '/home/u556423993/domains/noorwear.pk/public_html';

            $targetDirs = [
                "{$mainStoreDir}/{$clean}",
                "{$mainStoreDir}/subdomain/{$clean}",
                "/home/u556423993/domains/{$clean}.noorwear.pk/public_html",
            ];

            // Wait a brief moment if Hostinger is creating directory
            sleep(1);

            foreach ($targetDirs as $subdomainDir) {
                $parent = dirname($subdomainDir);
                if (is_dir($parent) || is_dir($subdomainDir)) {
                    if (!is_dir($subdomainDir)) {
                        @mkdir($subdomainDir, 0755, true);
                    }

                    if (is_dir($subdomainDir)) {
                        @unlink("{$subdomainDir}/default.php");

                        foreach (['index.html', 'favicon.svg', 'icons.svg', '.htaccess'] as $file) {
                            $source = "{$mainStoreDir}/{$file}";
                            if (file_exists($source)) {
                                @copy($source, "{$subdomainDir}/{$file}");
                            }
                        }

                        $this->copyDirectory("{$mainStoreDir}/assets", "{$subdomainDir}/assets");
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("linkStorefrontFiles handled exception for {$subdomain}: " . $e->getMessage());
        }
    }

    protected function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }

        $dir = @opendir($src);
        if (!$dir) {
            return;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = "{$src}/{$file}";
            $dstPath = "{$dst}/{$file}";

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
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
