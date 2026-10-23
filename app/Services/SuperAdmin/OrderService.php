<?php

namespace App\Services\SuperAdmin;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    /**
     * Get all orders across the platform with filtering and pagination.
     */
    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query()
            ->with([
                'company:id,name,subdomain',
                'user:id,name,email',
                'items.product:id,name,slug,price',
            ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get detailed information for a specific order.
     */
    public function getOrderDetails(Order|int|string $order): Order
    {
        if ($order instanceof Order) {
            $order->loadMissing([
                'company',
                'user',
                'items.product',
            ]);
            return $order;
        }

        return Order::with([
            'company',
            'user',
            'items.product',
        ])->findOrFail($order);
    }
}
