<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCategoryApiTest extends TestCase
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
            'name' => 'Test Category Store',
            'subdomain' => 'testcat-' . time(),
            'whatsapp_number' => '+92 300 7654321',
            'email' => 'catmerchant@test.com',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'name' => 'Category Admin',
            'email' => 'admin-' . time() . '@testcat.com',
            'password' => bcrypt('password123'),
            'role' => $adminRole->id,
        ]);

        $this->token = $this->adminUser->createToken('merchant_token')->plainTextToken;
    }

    public function test_merchant_can_add_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/company/products/category/add', [
                'name' => 'Running Shoes',
                'slug' => 'running-shoes',
                'description' => 'Athletic and running shoes collection.',
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Category created successfully.',
            ]);

        $this->assertDatabaseHas('product_categories', [
            'company_id' => $this->company->id,
            'name' => 'Running Shoes',
            'slug' => 'running-shoes',
            'is_active' => 1,
        ]);
    }

    public function test_merchant_can_fetch_categories(): void
    {
        ProductCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Casual Shirts',
            'slug' => 'casual-shirts',
            'description' => 'Comfortable daily shirts.',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/company/products/category');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertEquals('Casual Shirts', $data[0]['name']);
        $this->assertArrayHasKey('products_count', $data[0]);
    }

    public function test_merchant_can_delete_category(): void
    {
        $category = ProductCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Old Season',
            'slug' => 'old-season',
            'description' => 'To be deleted',
            'is_active' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/company/products/category/delete/' . $category->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Category deleted successfully.',
            ]);

        $this->assertDatabaseMissing('product_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_add_category_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/company/products/category/add', []);

        $response->assertStatus(422);
        $this->assertArrayHasKey('name', $response->json('data') ?? $response->json('errors') ?? []);
    }

    public function test_api_storage_route_serves_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('categories/test-image.png', 'fake image content');

        $response = $this->get('/api/storage/categories/test-image.png');
        $response->assertStatus(200);

        $response404 = $this->get('/api/storage/categories/non-existent.png');
        $response404->assertStatus(404);
    }

    public function test_web_storage_route_serves_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('categories/test-image-web.png', 'fake image content');

        $response = $this->get('/storage/categories/test-image-web.png');
        $response->assertStatus(200);
    }
}

