<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Role;
use App\Models\StoreSetting;
use App\Models\User;
use App\Traits\FileUploadTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    use FileUploadTrait;
    /**
     * Check company subdomain availability.
     */
    public function checkSubdomain(string $subdomain): array
    {
        $subdomain = Str::slug($subdomain);

        $reserved = [ 'admin', 'api','www', 'mail', 'dashboard','root','support',];

        if (in_array($subdomain, $reserved)) {
            return [
                'available' => false,
                'subdomain' => $subdomain,
                'message' => 'This subdomain is reserved.',
            ];
        }

        $exists = Company::where('subdomain', $subdomain)->exists();

        return [
            'available' => !$exists,
            'subdomain' => $subdomain,
            'message' => $exists
                ? 'Subdomain already taken.'
                : 'Subdomain is available.',
        ];
    }

    /**
     * Register company.
     */
    public function register(array $data): array
    {
        Log::info('Service Start');
        return DB::transaction(function () use ($data) {

            $subdomain = Str::slug($data['subdomain']);

            /*
             * Logo Upload
             */
            $logoPath = null;
            if (isset($data['logo']) && $data['logo']) {
                $logoPath = $this->uploadFile(
                    $data['logo'],
                    'companies/logos'
                );
            }

            /*
             * Create Company
             */
            $company = Company::create([
                'name' => $data['company_name'],
                'subdomain' => $subdomain,
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'logo' => $logoPath,
                'trial_ends_at' => now()->addMonth(),
                'status' => 'trial',
            ]);

            /*
             * Create Default Store Settings
             */
            StoreSetting::create([
                'company_id' => $company->id,
                'primary_color' => '#1E3A8A',
                'secondary_color' => '#3B82F6',
                'accent_color' => '#10B981',
                'header_bg_color' => '#FFFFFF',
                'footer_bg_color' => '#1F2937',
                'banners' => [],
                'use_custom_terms' => false,
                'terms_and_conditions' => null,
            ]);

            /*
             * Create Merchant Admin
             */
            $role = Role::where('name', 'Admin')->first();
            if(!$role){
                throw new HttpException(404,'Role not found');
            }

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $role->id,
            ]);

            /*
             * Generate Token
             */
            $token = $user->createToken('user-token')->plainTextToken;

            Log::info('Service retrun data');
            return [
                'token' => $token,
                'user' => $this->formatUser($user),
                'company' => $this->formatCompany($company),
            ];
        });
    }

    /**
     * Login company user.
     */
    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])
            ->with('company')
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new HttpException( 401,'Invalid email or password credentials.');
        }

        if (!$user->role()->where('name', 'Admin')->exists()) {
            throw new HttpException( 403,
                'Access denied. You are not authorized as a merchant.'
               
            );
        }

        $token = $user->createToken('user-token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $this->formatUser($user),
            'company' => $user->company
                ? $this->formatCompany($user->company)
                : null,
        ];
    }

   

    /**
     * Logout current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Format user response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    /**
     * Format company response.
     */
    private function formatCompany(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'subdomain' => $company->subdomain,
            'store_url' => "https://{$company->subdomain}."
                . config('app.main_domain', 'example.com'),
            'whatsapp_number' => $company->whatsapp_number,
            'logo_url' => $company->logo
                ? asset('storage/' . $company->logo)
                : null,
            'trial_ends_at' => $company->trial_ends_at?->toDateTimeString(),
            'trial_days_left' => $company->trial_ends_at
                ? (int) ceil(
                    now()->diffInDays(
                        $company->trial_ends_at,
                        false
                    )
                )
                : 0,
            'status' => $company->status,
        ];
    }
}