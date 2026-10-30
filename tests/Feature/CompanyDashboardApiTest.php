<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $adminUser;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'admin', 'description' => 'Administrator']
        );

        $this->company = Company::create([
            'name' => 'Test Dashboard Store',
            'subdomain' => 'testdash-' . time(),
            'whatsapp_number' => '+92 300 1234567',
            'email' => 'merchant@testdash.com',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Merchant Admin',
            'email' => 'admin-' . time() . '@testdash.com',
            'password' => bcrypt('password123'),
            'role' => $adminRole->id,
        ]);

        $this->token = $this->adminUser->createToken('merchant_token')->plainTextToken;
    }

    public function test_merchant_can_fetch_dashboard_analytics(): void
    {
        // 1. Create product and order
        $product = Product::create([
            'company_id' => $this->company->id,
            'title' => 'Test Premium Jacket',
            'slug' => 'test-premium-jacket-' . time(),
            'regular_price' => 100.00,
            'sale_price' => 90.00,
            'stock' => 20,
            'has_variants' => false,
            'is_available' => true,
        ]);

        $order = Order::create([
            'company_id' => $this->company->id,
            'order_number' => 'ORD-TEST-1001',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'customer_name' => 'Ali Khan',
            'customer_phone' => '+92 300 9876543',
            'shipping_address' => 'Mall Road, Lahore',
            'city' => 'Lahore',
            'subtotal' => 90.00,
            'total_amount' => 90.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'price' => 90.00,
            'quantity' => 1,
            'subtotal' => 90.00,
        ]);

        StoreVisit::create([
            'company_id' => $this->company->id,
            'ip_address' => '127.0.0.1',
            'visited_at' => now(),
        ]);

        // Request dashboard analytics
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/company/dashboard/analytics');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'revenueThisMonth',
                    'revenueGrowth',
                    'ordersThisMonth',
                    'ordersGrowth',
                    'storeVisitors',
                    'visitorsGrowth',
                    'avgOrderValue',
                    'aovGrowth',
                    'totalRevenue',
                    'totalOrders',
                    'chartData',
                    'recentOrders',
                    'summary' => [
                        'productsCount',
                        'categoriesCount',
                        'pendingOrdersCount',
                        'totalOrders',
                    ],
                ],
            ]);

        $this->assertEquals(90.00, $response->json('data.revenueThisMonth'));
        $this->assertEquals(1, $response->json('data.ordersThisMonth'));
        $this->assertCount(7, $response->json('data.chartData'));
        $this->assertNotEmpty($response->json('data.recentOrders'));
    }

    public function test_merchant_can_fetch_and_update_orders(): void
    {
        $order = Order::create([
            'company_id' => $this->company->id,
            'order_number' => 'ORD-TEST-2002',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'stripe',
            'customer_name' => 'Fatima Noor',
            'customer_phone' => '+92 321 1122334',
            'shipping_address' => 'Clifton, Karachi',
            'city' => 'Karachi',
            'subtotal' => 150.00,
            'total_amount' => 150.00,
        ]);

        // Get orders list
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/company/orders');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', true);

        // Update order status
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/company/orders/{$order->id}/status", [
                'status' => 'delivered',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.fulfillmentStatus', 'Delivered')
            ->assertJsonPath('data.paymentStatus', 'Paid');
    }
}
