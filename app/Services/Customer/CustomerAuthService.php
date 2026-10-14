<?php

namespace App\Services\Customer;

use App\Models\User;
use App\Models\CustomerOtp;
use App\Models\Otp;
use App\Services\Mail\MailService as MailMailService;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerAuthService
{
    public function __construct(
        private MailMailService $mailService
    ) {}


    // Register Customer 
    public function register(array $data, $company): array
    {
        $result = DB::transaction(function () use ($data, $company) {
            // Customer role database se resolve karein.
            $customerRoleId = DB::table('roles')
                ->whereRaw('LOWER(name) = ?', ['customer'])
                ->value('id');

            if (!$customerRoleId) {
                throw new \RuntimeException(
                    'Customer role is not configured in the roles table.'
                );
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => $customerRoleId,
                'company_id' => $company->id,
            ]);

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
                'otp' => $otp,
            ];
        });

        // Email transaction commit hone ke baad send hogi.
        $this->mailService->send(
            to: $result['user']->email,
            title: 'Verify Your Email',
            message: 'Use the OTP below to verify your email address.',
            name: $result['user']->name,
            otp: $result['otp'],
            expiry: 10,
        );

        return [
            'email' => $result['user']->email,
            'email_verified' => false,
        ];
    }

    // Resend Otp
    public function resendOtp(string $email, string $type): void
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw new HttpException(404,'User not found.');
        }

        $expiry = 5;
        $otpCode = (string) random_int(100000, 999999);

        // Save the new OTP and remove the previous OTP for this type.
        DB::transaction(function () use (
            $user,
            $type,
            $otpCode,
            $expiry,
        ) {
            Otp::where('user_id', $user->id)->where('type', $type)->delete();

            Otp::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'otp_hash' => $otpCode,
                'type' => $type,
                'expires_at' => now()->addMinutes($expiry),
            ]);
        });

        // Send the new OTP to the user.
        $this->mailService->send(
            to: $user->email,
            title: 'Your OTP Code',
            message: 'Your new verification code is given below.',
            name: $user->name,
            otp: $otpCode,
            expiry: $expiry,
        );
    }

    // Verify OTP
    public function verifyOtp(array $data, $company): array
    {
        $user = User::query()
            ->where('email', Str::lower($data['email']))
            ->where('company_id', $company->id)
            ->whereHas('role', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['customer']);
            })
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Customer account not found.'],
            ]);
        }

        if ($user->email_verified_at) {
            return ['email_verified' => true];
        }

        $otpRecord = Otp::where('user_id', $user->id)->where('type', 'register')->first();

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

        if ($data['otp'] !== $otpRecord->otp_hash) {
            $otpRecord->increment('attempts');
        
            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP.'],
            ]);
        
        }

        DB::transaction(function () use ($user, $otpRecord) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $otpRecord->delete();
        });

        return ['email_verified' => true];
    }

    // Login user
    public function login(array $data, $company): array
    {
        $user = User::query()
            ->where('email', Str::lower($data['email']))
            ->where('company_id', $company->id)
            ->whereHas('role', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['customer']);
            })
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->email_verified_at) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email before logging in.'],
            ]);
        }

        $token = $user->createToken('user')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
