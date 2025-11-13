<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@hr-system.com',
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'user_role_id' => 1,
            'user_type' => 'admin',
            'company_id' => 1,
            'company_name' => 'HR Company',
            'profile_photo' => '',
            'gender' => 'male',
            'is_active' => 1,
            'is_logged_in' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        User::create([
            'first_name' => 'HR',
            'last_name' => 'Manager',
            'email' => 'hr@hr-system.com',
            'username' => 'hr_manager',
            'password' => Hash::make('password123'),
            'user_role_id' => 2,
            'user_type' => 'hr',
            'company_id' => 1,
            'company_name' => 'HR Company',
            'profile_photo' => '',
            'gender' => 'female',
            'is_active' => 1,
            'is_logged_in' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        User::create([
            'first_name' => 'Department',
            'last_name' => 'Manager',
            'email' => 'manager@hr-system.com',
            'username' => 'dept_manager',
            'password' => Hash::make('password123'),
            'user_role_id' => 3,
            'user_type' => 'manager',
            'company_id' => 1,
            'company_name' => 'HR Company',
            'profile_photo' => '',
            'gender' => 'male',
            'is_active' => 1,
            'is_logged_in' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        User::create([
            'first_name' => 'Regular',
            'last_name' => 'Employee',
            'email' => 'employee@hr-system.com',
            'username' => 'employee',
            'password' => Hash::make('password123'),
            'user_role_id' => 4,
            'user_type' => 'employee',
            'company_id' => 1,
            'company_name' => 'HR Company',
            'profile_photo' => '',
            'gender' => 'female',
            'is_active' => 1,
            'is_logged_in' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Add more employees for testing
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'first_name' => 'Employee',
                'last_name' => $i,
                'email' => "emp{$i}@hr-system.com",
                'username' => "emp{$i}",
                'password' => Hash::make('password123'),
                'user_role_id' => 4,
                'user_type' => 'employee',
                'company_id' => 1,
                'company_name' => 'HR Company',
                'profile_photo' => '',
                'gender' => $i % 2 == 0 ? 'female' : 'male',
                'contact_number' => '010000000' . $i,
                'is_active' => 1,
                'is_logged_in' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
