<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'        => 'Admin',
                'slug'        => 'admin',
                'description' => 'Store / Merchant Administrator with full access to manage store',
            ],
            [
                'name'        => 'Customer',
                'slug'        => 'customer',
                'description' => 'Storefront Customer who places orders and browses catalog',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}