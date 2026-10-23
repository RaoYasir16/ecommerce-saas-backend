<?php

namespace App\Services\SuperAdmin;

use App\Models\Company;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\SubscriptionPlan;

class DashboardService
{
    public function getAnalytics(): array
    {
        return [
            'total_companies' => Company::count(),

            'total_users' => User::count(),

            'total_products' => Product::count(),

            'total_orders' => Order::count(),

            'total_subscription_plans' => SubscriptionPlan::count(),

            'total_sales' => (float) Order::sum('total_amount'),
        ];
    }
}
