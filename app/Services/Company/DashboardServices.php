<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StoreVisit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            ->with(['items.product.category'])
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
     * Normalize order status filter value (maps 'unfulfilled' alias to 'pending').
     */
    protected function normalizeOrderStatusFilter(?string $status): ?string
    {
        if ($status === null || trim($status) === '' || strtolower(trim($status)) === 'all') {
            return null;
        }

        $normalized = strtolower(trim($status));
        return $normalized === 'unfulfilled' ? 'pending' : $normalized;
    }

    /**
     * Apply shared search, status, payment_status, and category filters to an Order query.
     */
    protected function applyOrderFilters($query, array $filters = []): void
    {
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $status = $this->normalizeOrderStatusFilter($filters['status'] ?? null);
        if ($status !== null) {
            $query->where('status', $status);
        }

        if (!empty($filters['payment_status']) && strtolower((string) $filters['payment_status']) !== 'all') {
            $query->where('payment_status', strtolower(trim((string) $filters['payment_status'])));
        }

        $categoryId = $filters['category_id'] ?? null;
        $categoryName = trim((string) ($filters['category'] ?? ''));

        if ($categoryId !== null && $categoryId !== '' && strtolower((string) $categoryId) !== 'all') {
            $rawCatId = strtolower(trim((string) $categoryId));
            if ($rawCatId === 'uncategorized') {
                $query->whereHas('items', function ($q) {
                    $q->whereNull('product_id')
                        ->orWhereHas('product', fn($pq) => $pq->whereNull('category_id'));
                });
            } elseif (is_numeric($categoryId) && (int) $categoryId > 0) {
                $catId = (int) $categoryId;
                $query->whereHas('items.product', function ($q) use ($catId) {
                    $q->where('category_id', $catId);
                });
            }
        } elseif ($categoryName !== '' && strtolower($categoryName) !== 'all') {
            if (strtolower($categoryName) === 'uncategorized') {
                $query->whereHas('items', function ($q) {
                    $q->whereNull('product_id')
                        ->orWhereHas('product', fn($pq) => $pq->whereNull('category_id'));
                });
            } elseif (is_numeric($categoryName)) {
                $catId = (int) $categoryName;
                $query->whereHas('items.product', fn($q) => $q->where('category_id', $catId));
            } else {
                $query->whereHas('items.product.category', function ($q) use ($categoryName) {
                    $q->where('name', $categoryName);
                });
            }
        }
    }

    /**
     * Get all orders for the merchant company with optional filters.
     */
    public function getOrders(Company $company, array $filters = []): Collection
    {
        $query = Order::where('company_id', $company->id)
            ->with(['items.product.category']);

        $this->applyOrderFilters($query, $filters);

        return $query->latest('created_at')
            ->get()
            ->map(fn($o) => $this->formatOrder($o));
    }

    /**
     * Get status-wise and category-wise order counts for the merchant company.
     */
    public function getOrderSummary(Company $company, array $filters = []): array
    {
        $statusQuery = Order::where('company_id', $company->id);
        // Apply search / payment_status / category filter to status counts if provided (excluding status itself)
        $this->applyOrderFilters($statusQuery, [
            'search' => $filters['search'] ?? null,
            'payment_status' => $filters['payment_status'] ?? null,
            'category_id' => $filters['category_id'] ?? null,
        ]);

        $statusRows = $statusQuery
            ->selectRaw('status, COUNT(*) as orders_count')
            ->groupBy('status')
            ->get();

        $byStatus = [
            'pending' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];
        $totalOrders = 0;

        foreach ($statusRows as $row) {
            $key = strtolower((string) ($row->status ?? 'pending'));
            if ($key === 'unfulfilled') {
                $key = 'pending';
            }
            $cnt = (int) $row->orders_count;
            $byStatus[$key] = ($byStatus[$key] ?? 0) + $cnt;
            $totalOrders += $cnt;
        }

        // Category-wise distinct order count (respecting status / search / payment_status if active)
        $catQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.company_id', $company->id);

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $catQuery->where(function ($q) use ($search) {
                $q->where('orders.order_number', 'like', $search)
                    ->orWhere('orders.customer_name', 'like', $search)
                    ->orWhere('orders.customer_phone', 'like', $search)
                    ->orWhere('orders.customer_email', 'like', $search)
                    ->orWhere('orders.city', 'like', $search);
            });
        }

        $statusFilter = $this->normalizeOrderStatusFilter($filters['status'] ?? null);
        if ($statusFilter !== null) {
            $catQuery->where('orders.status', $statusFilter);
        }

        if (!empty($filters['payment_status']) && strtolower((string) $filters['payment_status']) !== 'all') {
            $catQuery->where('orders.payment_status', strtolower(trim((string) $filters['payment_status'])));
        }

        $catStatsRows = $catQuery
            ->selectRaw('products.category_id as category_id, COUNT(DISTINCT orders.id) as orders_count, COALESCE(SUM(order_items.quantity), 0) as items_count, COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->groupBy('products.category_id')
            ->get();

        $catCountMap = [];
        $uncategorizedStats = ['orders_count' => 0, 'items_count' => 0, 'revenue' => 0.0];

        foreach ($catStatsRows as $row) {
            if ($row->category_id === null) {
                $uncategorizedStats = [
                    'orders_count' => (int) $row->orders_count,
                    'items_count' => (int) $row->items_count,
                    'revenue' => round((float) $row->revenue, 2),
                ];
            } else {
                $catCountMap[(int) $row->category_id] = [
                    'orders_count' => (int) $row->orders_count,
                    'items_count' => (int) $row->items_count,
                    'revenue' => round((float) $row->revenue, 2),
                ];
            }
        }

        $categories = ProductCategory::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $byCategory = $categories->map(function ($cat) use ($catCountMap) {
            $stats = $catCountMap[(int) $cat->id] ?? ['orders_count' => 0, 'items_count' => 0, 'revenue' => 0.0];
            return [
                'category_id' => (int) $cat->id,
                'name' => $cat->name,
                'orders_count' => $stats['orders_count'],
                'items_count' => $stats['items_count'],
                'revenue' => $stats['revenue'],
            ];
        })->values()->all();

        if ($uncategorizedStats['orders_count'] > 0) {
            $byCategory[] = [
                'category_id' => 'uncategorized',
                'name' => 'Uncategorized',
                'orders_count' => $uncategorizedStats['orders_count'],
                'items_count' => $uncategorizedStats['items_count'],
                'revenue' => $uncategorizedStats['revenue'],
            ];
        }

        return [
            'total_orders' => $totalOrders,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
        ];
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
            ->with(['items.product.category'])
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

        return $this->formatOrder($order->fresh(['items.product.category']));
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

        $formattedItems = $order->items->map(function ($item) {
            $catId = $item->product?->category_id;
            $catName = $item->product?->category?->name ?? 'Uncategorized';

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'category_id' => $catId,
                'category_name' => $catName,
                'name' => $item->product_title,
                'sku' => $item->variant_sku ?: '',
                'qty' => $item->quantity,
                'price' => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
            ];
        })->values();

        $categoryIds = $formattedItems
            ->map(fn($i) => $i['category_id'] ?? 'uncategorized')
            ->unique()
            ->values()
            ->all();

        $categoryNames = $formattedItems
            ->pluck('category_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

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
            'category_ids' => $categoryIds,
            'categories' => $categoryNames,
            'primary_category' => $categoryNames[0] ?? 'Uncategorized',
            'date' => $order->created_at ? $order->created_at->diffForHumans() : 'Recently',
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : null,
            'items' => $formattedItems->all(),
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
