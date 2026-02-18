<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create Roles
        $admin = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'description' => 'Full system access',
        ]);

        $manager = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Manage orders and inventory',
        ]);

        $customer = Role::create([
            'name' => 'Customer',
            'slug' => 'customer',
            'description' => 'Regular customer',
        ]);

        // Create Permissions
        $permissions = [
            // User Management
            ['name' => 'View Users', 'slug' => 'view-users', 'module' => 'users'],
            ['name' => 'Create Users', 'slug' => 'create-users', 'module' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'edit-users', 'module' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'delete-users', 'module' => 'users'],

            // Order Management
            ['name' => 'View Orders', 'slug' => 'view-orders', 'module' => 'orders'],
            ['name' => 'Create Orders', 'slug' => 'create-orders', 'module' => 'orders'],
            ['name' => 'Edit Orders', 'slug' => 'edit-orders', 'module' => 'orders'],
            ['name' => 'Delete Orders', 'slug' => 'delete-orders', 'module' => 'orders'],
            ['name' => 'Manage Order Status', 'slug' => 'manage-order-status', 'module' => 'orders'],

            // Inventory Management
            ['name' => 'View Inventory', 'slug' => 'view-inventory', 'module' => 'inventory'],
            ['name' => 'Manage Inventory', 'slug' => 'manage-inventory', 'module' => 'inventory'],

            // Product Management
            ['name' => 'View Products', 'slug' => 'view-products', 'module' => 'products'],
            ['name' => 'Create Products', 'slug' => 'create-products', 'module' => 'products'],
            ['name' => 'Edit Products', 'slug' => 'edit-products', 'module' => 'products'],
            ['name' => 'Delete Products', 'slug' => 'delete-products', 'module' => 'products'],

            // Customer Permissions
            ['name' => 'Place Order', 'slug' => 'place-order', 'module' => 'orders'],
            ['name' => 'View Own Orders', 'slug' => 'view-own-orders', 'module' => 'orders'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Assign all permissions to admin
        $admin->permissions()->attach(Permission::all());

        // Assign manager permissions
        $manager->permissions()->attach(Permission::whereIn('slug', [
            'view-orders',
            'edit-orders',
            'manage-order-status',
            'view-inventory',
            'manage-inventory',
            'view-products',
            'edit-products',
        ])->get());

        // Assign customer permissions
        $customer->permissions()->attach(Permission::whereIn('slug', [
            'place-order',
            'view-own-orders',
            'view-products',
        ])->get());
    }
}