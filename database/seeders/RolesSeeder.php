<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        $adminRole  = Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        $sellerRole = Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
        $depoRole   = Role::firstOrCreate(['name' => 'depo',   'guard_name' => 'web']);

        // Create users
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );
        $seller = User::firstOrCreate(
            ['email' => 'seller@example.com'],
            ['name' => 'Seller', 'password' => Hash::make('password')]
        );
        $depo = User::updateOrCreate(
            ['email' => 'depo@aanahtar.com'],
            ['name' => 'Depo', 'password' => Hash::make('password')]
        );

        // Assign roles
        $admin->syncRoles([$adminRole]);
        $seller->syncRoles([$sellerRole]);
        $depo->syncRoles([$depoRole]);
    }
}