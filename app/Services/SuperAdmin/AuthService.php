<?php

namespace App\Services\SuperAdmin;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(array $data): array
    {
        $admin = Admin::where('email', $data['email'])->first();

        if (
            !$admin ||!Hash::check($data['password'], $admin->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if (!$admin->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your admin account is inactive.'],
            ]);
        }

        $admin->update([
            'last_login_at' => now(),
        ]);

        $token = $admin->createToken('admin')->plainTextToken;

        $admin->token = $token;

        return [
            'token' => $token,
            'admin' => $admin,
        ];
    }

    public function logout(Admin $admin): void
    {
        $admin->currentAccessToken()?->delete();
    }
}
