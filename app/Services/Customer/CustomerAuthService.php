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
        $user = User::where('email', Str::lower($email))->first();

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        $expiry = 10;
        $otpCode = (string) random_int(100000, 999999);

        // Save the new OTP and remove the previous OTP for this type.
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

        $title = match ($type) {
            'forget_password' => 'Your Password Reset Code',
            'reset_password', 'resetpassword' => 'Your Password Change Code',
            default => 'Your OTP Code',
        };

        $message = match ($type) {
            'forget_password' => 'Use the code below to reset your password. Valid for 10 minutes.',
            'reset_password', 'resetpassword' => 'Use the code below to confirm updating your account password. Valid for 10 minutes.',
            default => 'Your new verification code is given below. Valid for 10 minutes.',
        };

        // Send the new OTP to the user.
        $this->mailService->send(
            to: $user->email,
            title: $title,
            message: $message,
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

    /**
     * Customer Forgot Password - Send OTP to email.
     */
    public function forgotPasswordSendOtp(string $email, $company): array
    {
        $user = User::query()
            ->where('email', Str::lower($email))
            ->where('company_id', $company->id)
            ->whereHas('role', function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['customer']);
            })
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No customer account found with this email in this store.'],
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $expiry = 10;

        DB::transaction(function () use ($user, $otp, $expiry) {
            Otp::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'forget_password',
                ],
                [
                    'otp_hash' => $otp,
                    'expires_at' => now()->addMinutes($expiry),
                    'attempts' => 0,
                ]
            );
        });

        $storeName = $company->name ?: 'Store';
        $this->mailService->send(
            to: $user->email,
            title: "Reset Your {$storeName} Account Password",
            message: "You requested a password reset for your customer account at {$storeName}. Use the 6-digit OTP code below to set a new password. If you did not make this request, you can safely ignore this email.",
            name: $user->name,
            otp: $otp,
            expiry: $expiry,
        );

        return [
            'email' => $user->email,
            'message' => "A password reset code has been sent to {$user->email}.",
        ];
    }

    /**
     * Customer Forgot Password - Verify OTP.
     */
    public function forgotPasswordVerifyOtp(string $email, string $otp, $company): bool
    {
        $user = User::query()
            ->where('email', Str::lower($email))
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

        $otpRecord = Otp::where('user_id', $user->id)
            ->where('type', 'forget_password')
            ->first();

        if (!$otpRecord || $otpRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Verification code has expired. Please request a new one.'],
            ]);
        }

        if ($otpRecord->attempts >= 5) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Please request a new code.'],
            ]);
        }

        if ((string) $otp !== (string) $otpRecord->otp_hash) {
            $otpRecord->increment('attempts');
            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        return true;
    }

    /**
     * Customer Forgot Password - Reset password with OTP.
     */
    public function forgotPasswordReset(array $data, $company): array
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

        $otpRecord = Otp::where('user_id', $user->id)
            ->where('type', 'forget_password')
            ->first();

        if (!$otpRecord || $otpRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Verification code has expired. Please request a new one.'],
            ]);
        }

        if ($otpRecord->attempts >= 5) {
            throw ValidationException::withMessages([
                'otp' => ['Too many incorrect attempts. Please request a new code.'],
            ]);
        }

        if ((string) $data['otp'] !== (string) $otpRecord->otp_hash) {
            $otpRecord->increment('attempts');
            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        DB::transaction(function () use ($user, $data, $otpRecord) {
            $user->forceFill([
                'password' => Hash::make($data['password']),
            ])->save();

            $otpRecord->delete();

            // Revoke active sessions for security
            $user->tokens()->delete();
        });

        return [
            'email' => $user->email,
        ];
    }

    /**
     * Customer Authenticated Password Reset - Send OTP.
     */
    public function sendPasswordResetOtp(User $user, $company): array
    {
        $otp = (string) random_int(100000, 999999);
        $expiry = 10;

        DB::transaction(function () use ($user, $otp, $expiry) {
            Otp::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'reset_password',
                ],
                [
                    'otp_hash' => $otp,
                    'expires_at' => now()->addMinutes($expiry),
                    'attempts' => 0,
                ]
            );
        });

        $storeName = $company?->name ?: 'Store';
        $this->mailService->send(
            to: $user->email,
            title: "Confirm Your {$storeName} Password Reset",
            message: "You requested to change your password for your account at {$storeName}. Use the 6-digit OTP code below to confirm your new password.",
            name: $user->name,
            otp: $otp,
            expiry: $expiry,
        );

        $parts = explode('@', $user->email);
        $namePart = $parts[0] ?? '';
        $domainPart = $parts[1] ?? '';
        $maskedName = strlen($namePart) > 2
            ? substr($namePart, 0, 1) . str_repeat('*', max(1, strlen($namePart) - 2)) . substr($namePart, -1)
            : $namePart . '*';
        $maskedEmail = "{$maskedName}@{$domainPart}";

        return [
            'email' => $user->email,
            'masked_email' => $maskedEmail,
        ];
    }

    /**
     * Customer Authenticated Password Reset - Update password with OTP.
     */
    public function resetPassword(User $user, array $data): array
    {
        if (!empty($data['current_password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password entered is incorrect.'],
                ]);
            }
        }

        $otpRecord = Otp::where('user_id', $user->id)
            ->where('type', 'reset_password')
            ->first();

        if (!$otpRecord || $otpRecord->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Verification code has expired. Please request a new OTP.'],
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
                'otp' => ['Invalid verification code.'],
            ]);
        }

        DB::transaction(function () use ($user, $data, $otpRecord) {
            $user->forceFill([
                'password' => Hash::make($data['password']),
            ])->save();

            $otpRecord->delete();
        });

        return [
            'success' => true,
        ];
    }
}
