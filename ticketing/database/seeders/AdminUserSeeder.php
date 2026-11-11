<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@jejak.test'],
            [
                'uuid' => (string) Str::ulid(),
                'name' => 'Super Admin',
                'phone' => '081234567890',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole(UserRole::ADMIN->value)) {
            $admin->assignRole(UserRole::ADMIN->value);
        }
    }
}
