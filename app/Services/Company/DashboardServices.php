<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StoreVisit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardServices
{
    /**
     * Calculate and return live analytics matching Merchant Dashboard design.
     */
    public function getAnalytics(Company $company): array
    {
        $companyId = $company->id;
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        // 1. Current Month / Last 30 Days Stats
        $ordersThisMonth = Order::where('company_id', $companyId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $revenueThisMonth = (float) Order::where('company_id', $companyId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        // 2. Previous 30 Days (30-60 days ago) for Growth Calculation
        $prevOrders = Order::where('company_id', $companyId)
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();

        $prevRevenue = (float) Order::where('company_id', $companyId)
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        // Growth percentages
        $revenueGrowth = $this->calculateGrowth($revenueThisMonth, $prevRevenue);
        $ordersGrowth = $this->calculateGrowth($ordersThisMonth, $prevOrders);

        // 3. Storefront Visitors
        $currentVisitors = StoreVisit::where('company_id', $companyId)
            ->where('visited_at', '>=', $thirtyDaysAgo)
            ->count();

        $prevVisitors = StoreVisit::where('company_id', $companyId)
            ->whereBetween('visited_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->count();

        $visitorsGrowth = $this->calculateGrowth($currentVisitors, $prevVisitors);

        // 4. Avg. Order Value (AOV)
        $avgOrderValue = $ordersThisMonth > 0
            ? round($revenueThisMonth / $ordersThisMonth, 2)
            : 0;

        $prevAvgOrderValue = $prevOrders > 0
            ? round($prevRevenue / $prevOrders, 2)
            : 0;

        $aovGrowth = $this->calculateGrowth($avgOrderValue, $prevAvgOrderValue);

        // 5. Total all-time metrics
        $totalOrders = Order::where('company_id', $companyId)->count();
        $totalRevenue = (float) Order::where('company_id', $companyId)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $pendingOrdersCount = Order::where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        // 6. 7-Day Chart Data (Weekly Sales on Subdomain)
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayDate = $now->copy()->subDays($i);
            $dayStart = $dayDate->copy()->startOfDay();
            $dayEnd = $dayDate->copy()->endOfDay();

            $daySales = (float) Order::where('company_id', $companyId)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount');

            $dayOrders = Order::where('company_id', $companyId)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $chartData[] = [
                'day' => $dayDate->format('D'),
                'date' => $dayDate->format('Y-m-d'),
                'sales' => round($daySales, 2),
                'orders' => $dayOrders,
            ];
        }

        // 7. Recent Subdomain Orders Preview (Latest 6)
        $recentOrders = Order::where('company_id', $companyId)
            ->with(['items'])
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn($o) => $this->formatOrder($o));

        // 8. Platform Summary Counts
        $totalProducts = Product::where('company_id', $companyId)->count();
        $totalCategories = ProductCategory::where('company_id', $companyId)->count();

        return [
            // Metrics required by DashboardTab
            'revenueThisMonth' => round($revenueThisMonth, 2),
            'revenueGrowth' => $revenueGrowth,
            'ordersThisMonth' => $ordersThisMonth,
            'ordersGrowth' => $ordersGrowth,
            'storeVisitors' => $currentVisitors,
            'visitorsGrowth' => $visitorsGrowth,
            'avgOrderValue' => round($avgOrderValue, 2),
            'aovGrowth' => $aovGrowth,
            'totalRevenue' => round($totalRevenue, 2),
            'totalOrders' => $totalOrders,
            'pendingOrdersCount' => $pendingOrdersCount,
            'chartData' => $chartData,
            'recentOrders' => $recentOrders,
            'summary' => [
                'productsCount' => $totalProducts,
                'categoriesCount' => $totalCategories,
                'pendingOrdersCount' => $pendingOrdersCount,
                'totalOrders' => $totalOrders,
            ],
        ];
    }

    /**
     * Get all orders for the merchant company with optional filters.
     */
    public function getOrders(Company $company, array $filters = []): Collection
    {
        $query = Order::where('company_id', $company->id)
            ->with(['items']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status']) && strtolower($filters['status']) !== 'all') {
            $status = strtolower($filters['status']);
            if ($status === 'unfulfilled') {
                $status = 'pending';
            }
            $query->where('status', $status);
        }

        return $query->latest('created_at')
            ->get()
            ->map(fn($o) => $this->formatOrder($o));
    }

    /**
     * Get single order by ID or order_number.
     */
    public function getOrder(Company $company, int|string $id): ?array
    {
        $order = Order::where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('order_number', $id);
            })
            ->with(['items.product'])
            ->first();

        return $order ? $this->formatOrder($order) : null;
    }

    /**
     * Update order fulfillment and payment status.
     */
    public function updateOrderStatus(Company $company, int|string $id, array $data): array
    {
        $order = Order::where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('order_number', $id);
            })
            ->firstOrFail();

        if (isset($data['status'])) {
            $status = strtolower($data['status']);
            if ($status === 'unfulfilled') {
                $status = 'pending';
            }
            $order->status = $status;

            if ($status === 'delivered' && $order->payment_status === 'pending') {
                $order->payment_status = 'paid';
            }
        }

        if (isset($data['payment_status'])) {
            $order->payment_status = strtolower($data['payment_status']);
        }

        $order->save();

        return $this->formatOrder($order->fresh(['items']));
    }

    /**
     * Format Order model into frontend design contract.
     */
    public function formatOrder(Order $order): array
    {
        $fulfillmentMap = [
            'pending' => 'Unfulfilled',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        $payMethodMap = [
            'cod' => 'Cash on Delivery',
            'cash_on_delivery' => 'Cash on Delivery',
            'stripe' => 'Credit Card / Stripe',
            'card' => 'Credit Card',
            'jazzcash' => 'JazzCash / EasyPaisa',
            'easypaisa' => 'EasyPaisa',
        ];

        $rawMethod = strtolower($order->payment_method ?? 'cod');
        $paymentMethod = $payMethodMap[$rawMethod] ?? ucwords(str_replace('_', ' ', $order->payment_method ?? 'Cash on Delivery'));
        $fulfillmentStatus = $fulfillmentMap[strtolower($order->status ?? 'pending')] ?? ucfirst($order->status ?? 'Unfulfilled');
        $paymentStatus = ucfirst(strtolower($order->payment_status ?? 'Pending'));

        $itemsCount = $order->items->sum('quantity') ?: max($order->items->count(), 1);

        return [
            'id' => $order->order_number ?: ('ORD-' . $order->id),
            'raw_id' => $order->id,
            'customer' => $order->customer_name ?: 'Guest Customer',
            'email' => $order->customer_email ?: '',
            'city' => $order->city ?: 'Storefront',
            'phone' => $order->customer_phone ?: '',
            'shipping_address' => $order->shipping_address ?: '',
            'postal_code' => $order->postal_code ?: '',
            'notes' => $order->notes ?: '',
            'itemsCount' => $itemsCount,
            'subtotal' => (float) $order->subtotal,
            'shipping_fee' => (float) $order->shipping_fee,
            'tax' => (float) $order->tax,
            'discount' => (float) $order->discount,
            'total' => (float) $order->total_amount,
            'paymentMethod' => $paymentMethod,
            'paymentStatus' => $paymentStatus,
            'fulfillmentStatus' => $fulfillmentStatus,
            'date' => $order->created_at ? $order->created_at->diffForHumans() : 'Recently',
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product_title,
                    'sku' => $item->variant_sku ?: '',
                    'qty' => $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Helper to compute percentage growth string e.g. "+18.4%" or "-5.1%".
     */
    protected function calculateGrowth(float|int $current, float|int $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '+0.0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign . number_format($change, 1) . '%';
    }
}
