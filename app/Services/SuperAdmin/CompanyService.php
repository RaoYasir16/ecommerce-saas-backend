<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use App\Models\PlatformSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    /**
     * Get all companies with filtering, counts, and pagination.
     */
    public function getAllCompanies(array $filters = []): LengthAwarePaginator
    {
        $query = Company::query()
            ->with(['storeSetting', 'wallet', 'subscription.plan'])
            ->withCount(['users', 'products', 'orders']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp_number', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;
        $defaultCurrency = PlatformSetting::instance()->currency ?? 'PKR';

        return $query->latest()->paginate($perPage)->through(
            fn (Company $company) => $this->appendSummaryAttributes($company, $defaultCurrency)
        );
    }

    /**
     * Get company details by id or instance.
     */
    public function getCompanyDetails(Company|int|string $company): Company
    {
        $defaultCurrency = PlatformSetting::instance()->currency ?? 'PKR';

        if ($company instanceof Company) {
            $company->loadMissing(['storeSetting', 'wallet', 'subscription.plan']);
            $company->loadCount(['users', 'products', 'orders']);
            return $this->appendSummaryAttributes($company, $defaultCurrency);
        }

        $resolved = Company::with(['storeSetting', 'wallet', 'subscription.plan'])
            ->withCount(['users', 'products', 'orders'])
            ->findOrFail($company);

        return $this->appendSummaryAttributes($resolved, $defaultCurrency);
    }

    /**
     * Update company status (e.g. active, suspended, trial, expired).
     */
    public function updateCompanyStatus(Company|int|string $company, string $status): Company
    {
        $companyInstance = $company instanceof Company ? $company : Company::findOrFail($company);
        $companyInstance->update(['status' => $status]);

        return $companyInstance->fresh();
    }

    private function appendSummaryAttributes(Company $company, string $defaultCurrency): Company
    {
        $plan = $company->subscription?->plan;
        $company->setAttribute('subscription_plan', $plan);
        $company->setAttribute('plan', $plan?->name);
        $company->setAttribute('wallet_balance', (float) ($company->wallet?->balance ?? 0));
        $company->setAttribute('wallet_status', $company->wallet?->status ?? 'active');
        $company->setAttribute('wallet_currency', $company->wallet?->currency ?? $company->currency ?? $defaultCurrency);
        $company->setAttribute('phone', $company->whatsapp_number);

        return $company;
    }
}
