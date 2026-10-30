<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected ProductCategory $category;
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
            'name' => 'Product Test Store',
            'subdomain' => 'prodstore-' . time(),
            'whatsapp_number' => '+92 300 9988776',
            'email' => 'prodmerchant@test.com',
            'status' => 'active',
        ]);

        $this->category = ProductCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Footwear',
            'slug' => 'footwear',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Product Admin',
            'email' => 'admin-' . time() . '@prodtest.com',
            'password' => bcrypt('password123'),
            'role' => $adminRole->id,
        ]);

        $this->token = $this->adminUser->createToken('merchant_token')->plainTextToken;
    }

    public function test_merchant_can_create_product(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/company/products/create', [
                'title' => 'Air Pro Sneakers',
                'category_id' => $this->category->id,
                'slug' => 'air-pro-sneakers',
                'description' => 'High performance lightweight running sneakers.',
                'regular_price' => 129.99,
                'sale_price' => 99.99,
                'stock' => 50,
                'is_available' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Product created successfully.',
            ]);

        $this->assertDatabaseHas('products', [
            'company_id' => $this->company->id,
            'title' => 'Air Pro Sneakers',
            'slug' => 'air-pro-sneakers',
            'stock' => 50,
        ]);
    }

    public function test_merchant_can_fetch_products(): void
    {
        Product::create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'title' => 'Urban Jacket',
            'slug' => 'urban-jacket',
            'regular_price' => 149.00,
            'stock' => 15,
            'is_available' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/company/products');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);

        $json = $response->json();
        $this->assertTrue($json['status']);
    }

    public function test_merchant_can_update_product(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'title' => 'Classic Leather Boots',
            'slug' => 'classic-leather-boots',
            'regular_price' => 199.00,
            'stock' => 10,
            'is_available' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/company/products/update/' . $product->id, [
                'title' => 'Classic Leather Boots Updated',
                'regular_price' => 179.00,
                'stock' => 8,
                'is_available' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Product updated successfully.',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'title' => 'Classic Leather Boots Updated',
            'stock' => 8,
            'is_available' => 0,
        ]);
    }

    public function test_merchant_can_delete_product(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'title' => 'Temporary Product',
            'slug' => 'temp-product',
            'regular_price' => 20.00,
            'stock' => 5,
            'is_available' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/company/products/delete/' . $product->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Product deleted successfully.',
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_merchant_can_create_and_update_product_with_variants(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/company/products/create', [
                'title' => 'Varsity Bomber Jacket',
                'category_id' => $this->category->id,
                'slug' => 'varsity-bomber-jacket',
                'regular_price' => 199.99,
                'stock' => 30,
                'is_available' => true,
                'has_variants' => true,
                'variants' => [
                    [
                        'sku' => 'VBJ-M-BLK',
                        'regular_price' => 199.99,
                        'sale_price' => 179.99,
                        'stock' => 10,
                        'is_available' => true,
                        'attributes' => ['Size' => 'M', 'Color' => 'Black'],
                    ],
                    [
                        'sku' => 'VBJ-L-BLK',
                        'regular_price' => 199.99,
                        'stock' => 20,
                        'is_available' => true,
                        'attributes' => ['Size' => 'L', 'Color' => 'Black'],
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $productId = $response->json('data.id');
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'has_variants' => 1,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $productId,
            'sku' => 'VBJ-M-BLK',
            'stock' => 10,
        ]);

        // Update product variants
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/company/products/update/' . $productId, [
                'has_variants' => true,
                'variants' => [
                    [
                        'sku' => 'VBJ-XL-RED',
                        'regular_price' => 210.00,
                        'stock' => 15,
                        'is_available' => true,
                        'attributes' => ['Size' => 'XL', 'Color' => 'Red'],
                    ],
                ],
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $productId,
            'sku' => 'VBJ-XL-RED',
            'stock' => 15,
        ]);
    }
}
