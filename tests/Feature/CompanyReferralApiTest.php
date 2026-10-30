<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Models\CompanyWallet;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyReferralApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $referrerCompany;
    protected User $referrerUser;
    protected string $referrerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );

        // Platform settings
        PlatformSetting::firstOrCreate(
            ['id' => 1],
            [
                'referral_commission_percentage' => 5.00,
                'is_referral_enabled' => true,
                'currency' => 'PKR',
            ]
        );

        // Create Referrer Company
        $this->referrerCompany = Company::create([
            'name' => 'Alpha Mega Store',
            'subdomain' => 'alpha-mega',
            'whatsapp_number' => '+923001234567',
            'email' => 'alpha@test.com',
            'status' => 'active',
            'referral_code' => 'REF-ALPHA1',
        ]);

        CompanyWallet::create([
            'company_id' => $this->referrerCompany->id,
            'balance' => 0.00,
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $this->referrerUser = User::create([
            'company_id' => $this->referrerCompany->id,
            'name' => 'Alpha Owner',
            'email' => 'alpha@test.com',
            'password' => bcrypt('password123'),
            'role' => $adminRole->id,
        ]);

        $this->referrerToken = $this->referrerUser->createToken('merchant_token')->plainTextToken;
    }

    public function test_new_company_can_register_with_referral_code(): void
    {
        $response = $this->postJson('/api/company/register', [
            'name' => 'Beta Merchant',
            'company_name' => 'Beta Fashion Store',
            'subdomain' => 'beta-fashion',
            'email' => 'beta@test.com',
            'whatsapp_number' => '+923009876543',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'referral_code' => 'REF-ALPHA1',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
            ]);

        $betaCompany = Company::where('subdomain', 'beta-fashion')->first();
        $this->assertNotNull($betaCompany);
        $this->assertEquals($this->referrerCompany->id, $betaCompany->referred_by);
        $this->assertNotNull($betaCompany->referral_code);
    }

    public function test_referral_commission_is_credited_when_referred_company_buys_subscription(): void
    {
        // 1. Create referred company Beta
        $betaCompany = Company::create([
            'name' => 'Beta Store',
            'subdomain' => 'beta-store',
            'whatsapp_number' => '+923009876543',
            'email' => 'beta@test.com',
            'status' => 'active',
            'referred_by' => $this->referrerCompany->id,
            'referral_code' => 'REF-BETA22',
        ]);

        $betaWallet = CompanyWallet::create([
            'company_id' => $betaCompany->id,
            'balance' => 10000.00, // Sufficient balance
            'currency' => 'PKR',
            'status' => 'active',
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Administrator']
        );

        $betaUser = User::create([
            'company_id' => $betaCompany->id,
            'name' => 'Beta Admin',
            'email' => 'beta@test.com',
            'password' => bcrypt('password123'),
            'role' => $adminRole->id,
        ]);

        $betaToken = $betaUser->createToken('merchant_token')->plainTextToken;

        // 2. Create subscription plan (price = 2000 PKR)
        $plan = SubscriptionPlan::create([
            'name' => 'Growth Plan',
            'slug' => 'growth-plan',
            'price' => 2000.00,
            'billing_cycle' => 'monthly',
            'product_limit' => 50,
            'order_limit' => 200,
            'is_active' => true,
        ]);

        // 3. Purchase plan as Beta
        $purchaseResponse = $this->withHeader('Authorization', 'Bearer ' . $betaToken)
            ->postJson('/api/company/subscription/purchase', [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
            ]);

        $purchaseResponse->assertStatus(200);

        // 4. Verify Referrer (Alpha) Wallet credited with 5% of 2000 = 100 PKR
        $referrerWallet = CompanyWallet::where('company_id', $this->referrerCompany->id)->first();
        $this->assertEquals(100.00, (float) $referrerWallet->balance);

        // 5. Verify Referral API response for Referrer
        auth()->forgetGuards();
        $referralResponse = $this->withHeader('Authorization', 'Bearer ' . $this->referrerToken)
            ->getJson('/api/company/referrals');

        $referralResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'referral_code' => 'REF-ALPHA1',
                    'commission_percentage' => 5.0,
                    'metrics' => [
                        'total_referrals' => 1,
                        'total_earnings' => 100.0,
                    ],
                ],
            ]);
    }

    public function test_super_admin_can_view_and_update_referral_commission_setting(): void
    {
        $superAdmin = Admin::create([
            'name' => 'Platform SuperAdmin',
            'email' => 'superadmin@shopflow.com',
            'password' => bcrypt('adminsecret'),
            'role' => 'super_admin',
        ]);

        $adminToken = $superAdmin->createToken('admin_token')->plainTextToken;

        // 1. Get current settings
        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/super-admin/platform-settings');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'referral_commission_percentage' => 5,
                ],
            ]);

        // 2. Update commission percentage to 8.5%
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/super-admin/platform-settings', [
                'referral_commission_percentage' => 8.5,
                'is_referral_enabled' => true,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'referral_commission_percentage' => 8.5,
                ],
            ]);

        $this->assertEquals(8.5, PlatformSetting::getReferralCommissionPercentage());
    }
}
