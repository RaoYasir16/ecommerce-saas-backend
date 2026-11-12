<?php

namespace App\Services\SuperAdmin;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyWallet;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WalletDepositRequest;
use Carbon\Carbon;

class DashboardService
{
    public function getAnalytics(): array
    {
        $now = Carbon::now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $totalCompanies = Company::count();
        $activeCompanies = Company::whereIn('status', ['active', 'trial'])->count();
        $thisMonthCompanies = Company::where('created_at', '>=', $currentMonthStart)->count();

        $totalOrders = Order::count();
        $deliveredOrdersCount = Order::where('status', 'delivered')->count();
        $currentMonthOrders = Order::where('created_at', '>=', $currentMonthStart)->count();
        $lastMonthOrders = Order::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $totalSales = (float) Order::where('status', 'delivered')->sum('total_amount');
        $currentMonthSales = (float) Order::where('status', 'delivered')
            ->where('created_at', '>=', $currentMonthStart)
            ->sum('total_amount');
        $lastMonthSales = (float) Order::where('status', 'delivered')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_amount');

        $setting = PlatformSetting::instance();
        $currency = $setting->currency ?? 'PKR';

        $recentActivities = ActivityLog::with(['company:id,name,subdomain', 'causer'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (ActivityLog $log) {
                $companyName = $log->company?->name;
                $causerName = $log->causer?->name ?? 'System';
                $subtitleParts = array_filter([$companyName, $causerName]);

                return [
                    'id' => $log->id,
                    'title' => $log->description ?: ucfirst(str_replace('_', ' ', $log->log_name ?? 'Activity')),
                    'subtitle' => !empty($subtitleParts) ? implode(' • ', $subtitleParts) : ($log->log_name ?? 'platform'),
                    'log_name' => $log->log_name ?? 'general',
                    'event' => $log->event ?? 'logged',
                    'created_at' => $log->created_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        $recentOrders = Order::with(['company:id,name,subdomain,currency'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (Order $order) use ($currency) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'company_id' => $order->company_id,
                    'company_name' => $order->company?->name ?? 'Store',
                    'subdomain' => $order->company?->subdomain ?? '',
                    'customer_name' => $order->customer_name ?? 'Guest Customer',
                    'status' => $order->status ?? 'pending',
                    'payment_status' => $order->payment_status ?? 'unpaid',
                    'total_amount' => (float) $order->total_amount,
                    'currency' => $order->company?->currency ?: $currency,
                    'created_at' => $order->created_at?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return [
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'total_users' => User::count(),
            'total_products' => Product::count(),
            'total_orders' => $totalOrders,
            'delivered_orders_count' => $deliveredOrdersCount,
            'total_subscription_plans' => SubscriptionPlan::count(),
            'total_sales' => $totalSales,
            'frozen_wallets_count' => CompanyWallet::where('status', 'frozen')->count(),
            'pending_deposits_count' => WalletDepositRequest::where('status', 'pending')->count(),
            'currency' => $currency,
            'growth' => [
                'this_month_companies' => $thisMonthCompanies,
                'revenue_percentage' => $this->calculateGrowthPercentage($currentMonthSales, $lastMonthSales),
                'orders_percentage' => $this->calculateGrowthPercentage((float) $currentMonthOrders, (float) $lastMonthOrders),
            ],
            'recent_activity' => $recentActivities,
            'recent_orders' => $recentOrders,
        ];
    }

    private function calculateGrowthPercentage(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
