<?php

namespace App\Services\SuperAdmin;

use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class SubscriptionPlanService
{
    /**
     * Get all subscription plans with optional filtering.
     */
    public function getAllPlans(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = SubscriptionPlan::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['billing_cycle'])) {
            $query->where('billing_cycle', $filters['billing_cycle']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('price', 'asc');

        if (!empty($filters['per_page'])) {
            return $query->paginate((int) $filters['per_page']);
        }

        // Return all or paginate by default 15
        if (isset($filters['all']) && filter_var($filters['all'], FILTER_VALIDATE_BOOLEAN)) {
            return $query->get();
        }

        return $query->paginate(15);
    }

    /**
     * Create a new subscription plan.
     */
    public function createPlan(array $data): SubscriptionPlan
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name']);
        } else {
            $data['slug'] = Str::slug($data['slug']);
        }

        return SubscriptionPlan::create($data);
    }

    /**
     * Get single subscription plan.
     */
    public function getPlanById(SubscriptionPlan|int|string $plan): SubscriptionPlan
    {
        if ($plan instanceof SubscriptionPlan) {
            return $plan;
        }

        return SubscriptionPlan::findOrFail($plan);
    }

    /**
     * Update an existing subscription plan.
     */
    public function updatePlan(SubscriptionPlan|int|string $plan, array $data): SubscriptionPlan
    {
        $subscriptionPlan = $this->getPlanById($plan);

        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $subscriptionPlan->update($data);

        return $subscriptionPlan->fresh();
    }

    /**
     * Delete a subscription plan.
     */
    public function deletePlan(SubscriptionPlan|int|string $plan): bool
    {
        $subscriptionPlan = $this->getPlanById($plan);

        return (bool) $subscriptionPlan->delete();
    }

    /**
     * Generate unique slug for plan.
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            SubscriptionPlan::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
