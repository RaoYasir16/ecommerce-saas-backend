<?php

namespace App\Services\StoreCustomer;

use App\Models\Product;
use App\Models\ProductCategory;

class StoreService
{
    public function getStore($company): array
    {
        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain,
                'logo' => $company->logo,
                'logo_url' => $company->logo_url,
                'whatsapp_number' => $company->whatsapp_number,
                'email' => $company->email,
                'address' => $company->address,
                'status' => $company->status,
            ],
            'settings' => $company->storeSetting,
        ];
    }


    public function getProducts($company, array $filters = [])
    {
        $query = Product::query()
            ->where('company_id', $company->id)
            ->where('is_available', true)
            ->with(['category', 'images', 'variants']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 100);

        return $query->latest()->paginate($perPage);
    }

    public function getProduct($company, string $slug): Product
    {
        return Product::query()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_available', true)
            ->with(['category', 'images', 'variants'])
            ->firstOrFail();
    }

    public function getCategories($company)
    {
        return ProductCategory::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->latest()
            ->get();
    }
}
