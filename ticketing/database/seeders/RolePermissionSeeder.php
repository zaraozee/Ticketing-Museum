<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage platform settings',
            'verify organizers',
            'moderate museums',
            'view platform reports',
            'manage museum',
            'view museum reports',
            'scan tickets',
            'book tickets',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => UserRole::ADMIN->value]);
        $adminRole->givePermissionTo(Permission::all());

        $organizerRole = Role::firstOrCreate(['name' => UserRole::ORGANIZER->value]);
        $organizerRole->syncPermissions([
            'manage museum',
            'view museum reports',
            'scan tickets',
            'book tickets',
        ]);

        $userRole = Role::firstOrCreate(['name' => UserRole::USER->value]);
        $userRole->syncPermissions([
            'book tickets',
        ]);
    }
}
