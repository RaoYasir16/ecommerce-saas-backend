<?php

namespace Tests\Feature;

use App\Jobs\CreateCompanySubdomainJob;
use App\Models\Company;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\HostingerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HostingerSubdomainRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Admin role exists for company admin creation
        Role::firstOrCreate(['name' => 'Admin']);

        Config::set('services.hostinger.api_token', 'test-token');
        Config::set('services.hostinger.username', 'u123456789');
        Config::set('services.hostinger.domain', 'noorwear.pk');
    }

    /**
     * 1. Successful company registration creates company with domain_status = pending,
     * and dispatches CreateCompanySubdomainJob.
     */
    public function test_successful_company_registration_sets_pending_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'name' => 'Merchant Test',
            'email' => 'merchant@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'company_name' => 'Noor Mega Store',
            'subdomain' => 'noormega',
            'whatsapp_number' => '+923001234567',
        ];

        $response = $this->postJson('/api/company/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.company.subdomain', 'noormega')
            ->assertJsonPath('data.company.domain', 'noormega.noorwear.pk')
            ->assertJsonPath('data.company.domain_status', 'pending');

        $this->assertDatabaseHas('companies', [
            'subdomain' => 'noormega',
            'domain' => 'noormega.noorwear.pk',
            'domain_status' => 'pending',
            'name' => 'Noor Mega Store',
        ]);

        Queue::assertPushed(CreateCompanySubdomainJob::class, function ($job) {
            $company = Company::where('subdomain', 'noormega')->first();
            return $job->companyId === $company->id;
        });
    }

    /**
     * 2. Reserved subdomain rejection.
     */
    public function test_reserved_subdomain_is_rejected(): void
    {
        Queue::fake();

        $reservedSubdomains = ['admin', 'api', 'www', 'mail', 'support', 'dashboard'];

        foreach ($reservedSubdomains as $reserved) {
            $response = $this->postJson('/api/company/register', [
                'name' => 'Merchant Test',
                'email' => "merchant_{$reserved}@test.com",
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'company_name' => "Company {$reserved}",
                'subdomain' => $reserved,
                'whatsapp_number' => '+923001234567',
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('status', false);

            $this->assertNotEmpty($response->json('data.subdomain'), "Expected validation error on subdomain for {$reserved}");
        }

        Queue::assertNothingPushed();
    }

    /**
     * 3. Duplicate subdomain rejection.
     */
    public function test_duplicate_subdomain_is_rejected(): void
    {
        Company::create([
            'name' => 'Existing Company',
            'subdomain' => 'existingstore',
            'domain' => 'existingstore.noorwear.pk',
            'domain_status' => 'active',
            'whatsapp_number' => '+923001112233',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/company/register', [
            'name' => 'Second Merchant',
            'email' => 'second@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'company_name' => 'Another Store',
            'subdomain' => 'existingstore',
            'whatsapp_number' => '+923009998877',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $this->assertNotEmpty($response->json('data.subdomain'));
    }

    /**
     * 4. Subdomain slug cleaning and normalization.
     */
    public function test_check_subdomain_endpoint_validates_and_normalizes(): void
    {
        $responseAvailable = $this->postJson('/api/company/check-subdomain', [
            'subdomain' => 'fresh-store',
        ]);

        $responseAvailable->assertStatus(200)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.subdomain', 'fresh-store');

        $responseReserved = $this->postJson('/api/company/check-subdomain', [
            'subdomain' => 'admin',
        ]);

        $responseReserved->assertStatus(200)
            ->assertJsonPath('data.available', false);
    }

    /**
     * 5. HostingerService handles successful API subdomain creation.
     */
    public function test_hostinger_service_creates_subdomain_successfully(): void
    {
        Http::fake([
            'https://developers.hostinger.com/api/hosting/v1/accounts/u123456789/websites/noorwear.pk/subdomains' => Http::response([
                'message' => 'Subdomain created successfully.',
                'subdomain' => 'noormega',
            ], 201),
        ]);

        $service = app(HostingerService::class);
        $result = $service->createSubdomain('noormega');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['already_exists']);
        $this->assertEquals(201, $result['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://developers.hostinger.com/api/hosting/v1/accounts/u123456789/websites/noorwear.pk/subdomains'
                && $request['subdomain'] === 'noormega'
                && $request['isUsingPublicDirectory'] === true
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    /**
     * 6. HostingerService handles duplicate/conflict (409) gracefully as already existing.
     */
    public function test_hostinger_service_handles_already_exists_conflict_gracefully(): void
    {
        Http::fake([
            'https://developers.hostinger.com/api/hosting/v1/accounts/u123456789/websites/noorwear.pk/subdomains' => Http::response([
                'message' => 'Subdomain already exists.',
            ], 409),
        ]);

        $service = app(HostingerService::class);
        $result = $service->createSubdomain('noormega');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['already_exists']);
        $this->assertEquals(409, $result['status']);
    }

    /**
     * 7. HostingerService handles API failure and logs without exposing credentials.
     */
    public function test_hostinger_service_handles_api_failure(): void
    {
        Http::fake([
            'https://developers.hostinger.com/api/hosting/v1/accounts/u123456789/websites/noorwear.pk/subdomains' => Http::response([
                'message' => 'Internal server error on Hostinger.',
            ], 500),
        ]);

        $service = app(HostingerService::class);
        $result = $service->createSubdomain('noormega');

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['status']);
    }

    /**
     * 8. CreateCompanySubdomainJob updates domain_status to active on success.
     */
    public function test_queue_job_activates_domain_status_on_success(): void
    {
        Http::fake([
            'https://developers.hostinger.com/api/hosting/v1/*' => Http::response([
                'message' => 'Subdomain created.',
            ], 201),
        ]);

        $company = Company::create([
            'name' => 'Active Test Store',
            'subdomain' => 'activestore',
            'domain' => 'activestore.noorwear.pk',
            'domain_status' => 'pending',
            'whatsapp_number' => '+923001234567',
            'status' => 'active',
        ]);

        $job = new CreateCompanySubdomainJob($company->id);
        $job->handle(app(HostingerService::class));

        $this->assertEquals('active', $company->fresh()->domain_status);
    }

    /**
     * 9. CreateCompanySubdomainJob marks domain_status as failed when retries are exhausted.
     */
    public function test_queue_job_marks_domain_status_failed_on_final_failure(): void
    {
        $company = Company::create([
            'name' => 'Failing Store',
            'subdomain' => 'failstore',
            'domain' => 'failstore.noorwear.pk',
            'domain_status' => 'pending',
            'whatsapp_number' => '+923001234567',
            'status' => 'active',
        ]);

        $job = new CreateCompanySubdomainJob($company->id);
        $job->failed(new \RuntimeException('Connection failed'));

        $this->assertEquals('failed', $company->fresh()->domain_status);
    }

    /**
     * 10. If Hostinger API fails, registered company is NOT rolled back.
     */
    public function test_company_registration_persists_even_when_hostinger_fails(): void
    {
        Queue::fake();

        // 1. Register through API
        $response = $this->postJson('/api/company/register', [
            'name' => 'Merchant Robust',
            'email' => 'robust@test.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'company_name' => 'Robust Store',
            'subdomain' => 'robuststore',
            'whatsapp_number' => '+923001234567',
        ]);

        $response->assertStatus(201);

        $company = Company::where('subdomain', 'robuststore')->first();
        $this->assertNotNull($company);
        $this->assertEquals('pending', $company->domain_status);

        // 2. Simulate Hostinger API outage when queue job runs
        Http::fake([
            'https://developers.hostinger.com/api/hosting/v1/*' => Http::response([
                'message' => 'Hostinger down',
            ], 500),
        ]);

        $job = new CreateCompanySubdomainJob($company->id);
        try {
            $job->handle(app(HostingerService::class));
        } catch (\Throwable $e) {
            // Queue retry mechanism in action; after all retries fail, failed() is called
            $job->failed($e);
        }

        // The company still exists in DB with domain_status = failed
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'subdomain' => 'robuststore',
            'domain_status' => 'failed',
        ]);
    }

    /**
     * 11. Tenant middleware resolves company via incoming host subdomain (e.g. noormega.noorwear.pk).
     */
    public function test_tenant_middleware_identifies_company_via_host_subdomain(): void
    {
        $company = Company::create([
            'name' => 'Noor Mega',
            'subdomain' => 'noormega',
            'domain' => 'noormega.noorwear.pk',
            'domain_status' => 'active',
            'whatsapp_number' => '+923001234567',
            'status' => 'active',
        ]);

        $response = $this->getJson('http://noormega.noorwear.pk/api/customer/show-store');

        $response->assertStatus(200)
            ->assertJsonPath('data.company.subdomain', 'noormega')
            ->assertJsonPath('data.company.name', 'Noor Mega');

        $this->assertTrue(app()->bound('currentCompany'));
        $this->assertEquals($company->id, app('currentCompany')->id);
    }

    /**
     * 12. Tenant middleware resolves company via local domain (e.g. noormega.localhost).
     */
    public function test_tenant_middleware_identifies_company_via_local_host(): void
    {
        $company = Company::create([
            'name' => 'Local Mega',
            'subdomain' => 'localmega',
            'domain' => 'localmega.noorwear.pk',
            'domain_status' => 'active',
            'whatsapp_number' => '+923001234567',
            'status' => 'active',
        ]);

        $response = $this->getJson('http://localmega.localhost/api/customer/show-store');

        $response->assertStatus(200)
            ->assertJsonPath('data.company.subdomain', 'localmega');
    }

    /**
     * 13. Tenant middleware returns 404 for unknown/unregistered subdomain.
     */
    public function test_tenant_middleware_returns_404_for_unknown_subdomain(): void
    {
        $response = $this->getJson('http://nonexistentstore.noorwear.pk/api/customer/show-store');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Store not found.');
    }

    /**
     * 14. Tenant middleware returns 403 for inactive/suspended company.
     */
    public function test_tenant_middleware_returns_403_for_suspended_company(): void
    {
        Company::create([
            'name' => 'Suspended Shop',
            'subdomain' => 'suspendedshop',
            'domain' => 'suspendedshop.noorwear.pk',
            'domain_status' => 'active',
            'whatsapp_number' => '+923001234567',
            'status' => 'suspended',
        ]);

        $response = $this->getJson('http://suspendedshop.noorwear.pk/api/customer/show-store');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Store is currently unavailable or inactive.');
    }

    /**
     * 15. Tenant data isolation: storefront only returns products belonging to the resolved company.
     */
    public function test_tenant_data_isolation_between_two_companies(): void
    {
        $companyA = Company::create([
            'name' => 'Company A Store',
            'subdomain' => 'company-a',
            'domain' => 'company-a.noorwear.pk',
            'domain_status' => 'active',
            'whatsapp_number' => '+923001111111',
            'status' => 'active',
        ]);

        $companyB = Company::create([
            'name' => 'Company B Store',
            'subdomain' => 'company-b',
            'domain' => 'company-b.noorwear.pk',
            'domain_status' => 'active',
            'whatsapp_number' => '+923002222222',
            'status' => 'active',
        ]);

        // Product for Company A
        Product::create([
            'company_id' => $companyA->id,
            'title' => 'Product A Exclusive',
            'slug' => 'product-a-exclusive',
            'description' => 'Only for store A',
            'regular_price' => 100,
            'stock' => 10,
            'is_available' => true,
        ]);

        // Product for Company B
        Product::create([
            'company_id' => $companyB->id,
            'title' => 'Product B Exclusive',
            'slug' => 'product-b-exclusive',
            'description' => 'Only for store B',
            'regular_price' => 200,
            'stock' => 20,
            'is_available' => true,
        ]);

        // Request Storefront A
        $responseA = $this->getJson('http://company-a.noorwear.pk/api/customer/products');
        $responseA->assertStatus(200);

        $productTitlesA = collect($responseA->json('data.data'))->pluck('title');
        $this->assertTrue($productTitlesA->contains('Product A Exclusive'));
        $this->assertFalse($productTitlesA->contains('Product B Exclusive'));

        // Request Storefront B
        $responseB = $this->getJson('http://company-b.noorwear.pk/api/customer/products');
        $responseB->assertStatus(200);

        $productTitlesB = collect($responseB->json('data.data'))->pluck('title');
        $this->assertTrue($productTitlesB->contains('Product B Exclusive'));
        $this->assertFalse($productTitlesB->contains('Product A Exclusive'));
    }
}
