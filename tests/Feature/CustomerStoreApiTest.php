<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerStoreApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create or get Company
        $this->company = Company::firstOrCreate(
            ['subdomain' => 'teststore'],
            [
                'name' => 'Test Store',
                'status' => 'trial',
                'email' => 'store@example.com',
                'whatsapp_number' => '+1234567890',
            ]
        );

        // 2. Ensure customer role exists
        $customerRole = Role::firstOrCreate(
            ['slug' => 'customer'],
            ['name' => 'Customer', 'description' => 'Customer role']
        );

        // 3. Create or get Customer user
        $this->customer = User::firstOrCreate(
            ['email' => 'customer_test@example.com'],
            [
                'name' => 'John Customer',
                'company_id' => $this->company->id,
                'password' => bcrypt('password123'),
                'role' => $customerRole->id,
                'email_verified_at' => now(),
            ]
        );

        // 4. Create category & product
        $category = ProductCategory::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'test-category'],
            ['name' => 'Test Category', 'is_active' => true]
        );

        $this->product = Product::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'test-gadget'],
            [
                'category_id' => $category->id,
                'title' => 'Test Gadget',
                'regular_price' => 100.00,
                'sale_price' => 80.00,
                'stock' => 50,
                'is_available' => true,
                'has_variants' => false,
            ]
        );
    }

    public function test_customer_can_add_get_and_remove_favorite(): void
    {
        Sanctum::actingAs($this->customer, ['*'], 'user');

        // Add to favorite
        $response = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/favrot/add', [
                'product_id' => $this->product->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        // Get favorites
        $getResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->getJson('/api/customer/favrot/get-all');

        $getResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        // Remove from favorite
        $removeResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/favrot/remove', [
                'product_id' => $this->product->id,
            ]);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    public function test_customer_can_manage_cart(): void
    {
        Sanctum::actingAs($this->customer, ['*'], 'user');

        // Add to cart
        $addResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/add-to-cart/add', [
                'product_id' => $this->product->id,
                'quantity' => 2,
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.quantity', 2);

        // Get cart
        $getResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->getJson('/api/customer/add-to-cart/get-all');

        $getResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath('data.subtotal', 160);

        // Update cart quantity
        $updateResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/add-to-cart/update', [
                'product_id' => $this->product->id,
                'quantity' => 3,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.quantity', 3);

        // Remove from cart
        $removeResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->deleteJson('/api/customer/add-to-cart/remove', [
                'product_id' => $this->product->id,
            ]);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    public function test_customer_can_place_view_and_cancel_order(): void
    {
        Sanctum::actingAs($this->customer, ['*'], 'user');

        // First add item to cart
        $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/add-to-cart/add', [
                'product_id' => $this->product->id,
                'quantity' => 1,
            ]);

        // Place order from cart
        $orderResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/order/place', [
                'customer_name' => 'John Customer',
                'customer_phone' => '03001234567',
                'shipping_address' => '123 Main Boulevard, Model Town',
                'city' => 'Lahore',
                'postal_code' => '54000',
                'payment_method' => 'cod',
            ]);

        $orderResponse->assertStatus(201)
            ->assertJsonPath('status', true);

        $orderId = $orderResponse->json('data.id');

        // Get all orders
        $listResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->getJson('/api/customer/order/get-all');

        $listResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        // Get single order
        $singleResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->getJson('/api/customer/order/single/' . $orderId);

        $singleResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $orderId);

        // Cancel order
        $cancelResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/order/cancel/' . $orderId, [
                'reason' => 'Changed my mind',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_customer_can_add_view_and_delete_review(): void
    {
        Sanctum::actingAs($this->customer, ['*'], 'user');

        // Add review
        $addResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/customer/review/add', [
                'product_id' => $this->product->id,
                'rating' => 5,
                'review' => 'Excellent quality product! Highly recommended.',
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.rating', 5);

        $reviewId = $addResponse->json('data.id');

        // Get reviews for product
        $getResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->getJson('/api/customer/products/' . $this->product->id . '/reviews');

        $getResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        // Delete review
        $deleteResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->deleteJson('/api/customer/review/delete/' . $reviewId);

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', true);
    }

    public function test_variant_product_order_and_stock_restoration_on_cancel(): void
    {
        Sanctum::actingAs($this->customer, ['*'], 'user');

        // Create product with variant
        $variantProduct = Product::create([
            'company_id' => $this->company->id,
            'title' => 'T-Shirt With Variants',
            'slug' => 't-shirt-variant',
            'regular_price' => 50.00,
            'stock' => 0,
            'has_variants' => true,
            'is_available' => true,
        ]);

        $variant = \App\Models\ProductVariant::create([
            'product_id' => $variantProduct->id,
            'sku' => 'TSHIRT-RED-L',
            'regular_price' => 50.00,
            'sale_price' => 45.00,
            'stock' => 10,
            'attributes' => ['color' => 'Red', 'size' => 'L'],
            'is_available' => true,
        ]);

        // Add variant to cart via direct alias route (/api/cart/add)
        $addResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/cart/add', [
                'product_id' => $variantProduct->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('status', true);

        // Place order with direct items via /api/order/place
        $orderResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/order/place', [
                'customer_name' => 'Jane Customer',
                'customer_phone' => '03111234567',
                'shipping_address' => '456 Commercial Area',
                'items' => [
                    [
                        'product_id' => $variantProduct->id,
                        'product_variant_id' => $variant->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $orderResponse->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total_amount', '90.00');

        // Stock should have decreased from 10 to 8
        $this->assertEquals(8, $variant->fresh()->stock);

        $orderId = $orderResponse->json('data.id');

        // Cancel order -> Stock should be restored to 10
        $cancelResponse = $this->withHeader('X-Store-Subdomain', 'teststore')
            ->postJson('/api/order/cancel', [
                'order_id' => $orderId,
                'reason' => 'Ordered wrong size',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertEquals(10, $variant->fresh()->stock);
    }
}

