<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Get all users across the platform with filtering and pagination.
     */
    public function getAllUsers(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['company:id,name,subdomain,status', 'role:id,name'])
            ->withCount(['orders', 'favorites', 'reviews']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get users belonging to a specific company.
     */
    public function getCompanyUsers(Company|int|string $company, array $filters = []): LengthAwarePaginator
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        // Ensure company exists if scalar ID passed
        if (!($company instanceof Company)) {
            Company::findOrFail($companyId);
        }

        $query = User::query()
            ->where('company_id', $companyId)
            ->with('role:id,name')
            ->withCount(['orders', 'favorites', 'reviews']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get a specific user by id.
     */
    public function getUserById(User|int|string $user): User
    {
        if ($user instanceof User) {
            $user->loadMissing(['company', 'role']);
            $user->loadCount(['orders', 'favorites', 'reviews']);
            return $user;
        }

        return User::with(['company', 'role'])
            ->withCount(['orders', 'favorites', 'reviews'])
            ->findOrFail($user);
    }
}
