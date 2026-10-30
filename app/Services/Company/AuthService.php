<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Otp;
use App\Models\Role;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Mail\MailService;
use App\Traits\FileUploadTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    use FileUploadTrait;

    public function __construct(
        protected MailService $mailService
    ) {}

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
        $result = DB::transaction(function () use ($data) {
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
             * Referral Logic & Unique Code Generation
             */
            $referredBy = null;
            if (!empty($data['referral_code'])) {
                $referrer = Company::where('referral_code', trim($data['referral_code']))->first();
                if ($referrer) {
                    $referredBy = $referrer->id;
                }
            }

            $newReferralCode = 'REF-' . strtoupper(Str::random(6));
            while (Company::where('referral_code', $newReferralCode)->exists()) {
                $newReferralCode = 'REF-' . strtoupper(Str::random(6));
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
                'referral_code' => $newReferralCode,
                'referred_by' => $referredBy,
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
            if (!$role) {
                throw new HttpException(404, 'Role not found');
            }

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => $role->id,
            ]);

            /*
             * Generate OTP for registration verification
             */
            $otp = (string) random_int(100000, 999999);

            Otp::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'register',
                ],
                [
                    'otp_hash' => $otp,
                    'expires_at' => now()->addMinutes(10),
                    'attempts' => 0,
                ]
            );

            return [
                'user' => $user,
                'company' => $company,
                'otp' => $otp,
            ];
        });

        /*
         * Send Verification Email with OTP
         */
        $this->mailService->send(
            to: $result['user']->email,
            title: 'Verify Your Merchant Account',
            message: 'Welcome to your SaaS platform! Use the OTP below to verify your email address and activate your store.',
            name: $result['user']->name,
            otp: $result['otp'],
            expiry: 10,
        );

        return [
            'email' => $result['user']->email,
            'email_verified' => false,
            'company' => $this->formatCompany($result['company']),
        ];
    }

    /**
     * Verify merchant registration OTP.
     */
    public function verifyOtp(array $data): array
    {
        $user = User::where('email', Str::lower($data['email']))
            ->with('company')
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Merchant account not found.'],
            ]);
        }

        if ($user->email_verified_at) {
            $token = $user->createToken('user-token')->plainTextToken;
            return [
                'email_verified' => true,
                'token' => $token,
                'user' => $this->formatUser($user),
                'company' => $user->company ? $this->formatCompany($user->company) : null,
            ];
        }

        $otpRecord = Otp::where('user_id', $user->id)
            ->where('type', 'register')
            ->first();

        if (!$otpRecord || $otpRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['OTP has expired. Please request a new one.'],
            ]);
        }

        if ($otpRecord->attempts >= 5) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Please request a new OTP.'],
            ]);
        }

        if ((string) $data['otp'] !== (string) $otpRecord->otp_hash) {
            $otpRecord->increment('attempts');
            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP code.'],
            ]);
        }

        DB::transaction(function () use ($user, $otpRecord) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $otpRecord->delete();
        });

        $token = $user->createToken('user-token')->plainTextToken;

        return [
            'email_verified' => true,
            'token' => $token,
            'user' => $this->formatUser($user),
            'company' => $user->company ? $this->formatCompany($user->company) : null,
        ];
    }

    /**
     * Resend verification OTP.
     */
    public function resendOtp(string $email, string $type = 'register'): void
    {
        $user = User::where('email', Str::lower($email))->first();

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        $expiry = 10;
        $otpCode = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $type, $otpCode, $expiry) {
            Otp::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type,
                ],
                [
                    'otp_hash' => $otpCode,
                    'expires_at' => now()->addMinutes($expiry),
                    'attempts' => 0,
                ]
            );
        });

        $this->mailService->send(
            to: $user->email,
            title: 'Your Merchant Verification Code',
            message: 'Your new verification code is given below. Use it to activate your store.',
            name: $user->name,
            otp: $otpCode,
            expiry: $expiry,
        );
    }

    /**
     * Login company user.
     */
    public function login(array $data): array
    {
        $user = User::where('email', Str::lower($data['email']))
            ->with('company')
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new HttpException(401, 'Invalid email or password credentials.');
        }

        if (!$user->role()->where('name', 'Admin')->exists()) {
            throw new HttpException(403, 'Access denied. You are not authorized as a merchant.');
        }

        if (!$user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before logging in.'],
            ]);
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