<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        AdminUser::firstOrCreate(
            ['email' => 'admin@wabeo.fr'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
            ]
        );
    }
}
