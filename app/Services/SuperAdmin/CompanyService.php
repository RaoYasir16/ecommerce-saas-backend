<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    /**
     * Get all companies with filtering, counts, and pagination.
     */
    public function getAllCompanies(array $filters = []): LengthAwarePaginator
    {
        $query = Company::query()
            ->with('storeSetting')
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

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get company details by id or instance.
     */
    public function getCompanyDetails(Company|int|string $company): Company
    {
        if ($company instanceof Company) {
            $company->loadMissing('storeSetting');
            $company->loadCount(['users', 'products', 'orders']);
            return $company;
        }

        return Company::with('storeSetting')
            ->withCount(['users', 'products', 'orders'])
            ->findOrFail($company);
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
}
