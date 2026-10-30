<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter Store',
                'slug' => 'starter',
                'description' => 'Ideal for new sellers launching initial product drops.',
                'price' => 1000.00,
                'billing_cycle' => 'monthly',
                'currency' => 'PKR',
                'trial_days' => 30,
                'product_limit' => 50,
                'order_limit' => 200,
                'features' => [
                    'Up to 50 active products',
                    'Up to 200 orders per month',
                    'Free automated subdomain (store.shopflow.io)',
                    'Standard SSL certificate included',
                    'Standard email support',
                    'Basic order management & CSV export'
                ],
                'is_active' => true,
                'is_popular' => false,
            ],
            [
                'name' => 'Professional Brand',
                'slug' => 'pro',
                'description' => 'Most popular for growing brands scaling their e-commerce sales.',
                'price' => 2500.00,
                'billing_cycle' => 'monthly',
                'currency' => 'PKR',
                'trial_days' => 0,
                'product_limit' => 500,
                'order_limit' => 2000,
                'features' => [
                    'Up to 500 active products',
                    'Up to 2,000 orders per month',
                    'Custom domain support (www.yourbrand.com)',
                    'Automated Edge CDN & SSL caching',
                    'Storefront theme customizer & live preview',
                    'Priority WhatsApp & email support',
                    'Multiple payment gateways (COD, Raast, Wallet)'
                ],
                'is_active' => true,
                'is_popular' => true,
            ],
            [
                'name' => 'Enterprise Scale',
                'slug' => 'enterprise',
                'description' => 'For high-volume merchants requiring unlimited bandwidth & VIP support.',
                'price' => 6500.00,
                'billing_cycle' => 'monthly',
                'currency' => 'PKR',
                'trial_days' => 0,
                'product_limit' => null,
                'order_limit' => null,
                'features' => [
                    'Unlimited active products & catalog',
                    'Unlimited orders per month',
                    'Multiple custom domains & multi-stores',
                    '0% platform transaction fees',
                    'Dedicated account manager & 24/7 VIP support',
                    'Custom webhooks, API access & ERP integrations',
                    'Advanced realtime analytics & export'
                ],
                'is_active' => true,
                'is_popular' => false,
            ],
        ];

        foreach ($plans as $planData) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
