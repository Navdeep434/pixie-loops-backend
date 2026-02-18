<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@pixieloops.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Assign admin role
        $adminRole = Role::where('slug', 'admin')->first();
        $admin->roles()->attach($adminRole->id);

        // Create test customer
        $customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@pixieloops.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Assign customer role
        $customerRole = Role::where('slug', 'customer')->first();
        $customer->roles()->attach($customerRole->id);

        $this->command->info('Admin and test users created successfully!');
        $this->command->info('Admin: admin@pixieloops.com / password');
        $this->command->info('Customer: customer@pixieloops.com / password');
    }
}