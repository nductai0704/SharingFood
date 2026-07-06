<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Tạo các tài khoản mẫu nếu chưa có
        if (\App\Models\User::count() === 0) {
            \App\Models\User::create([
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'email_verified_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'admin',
                'phone' => '0901234567',
                'address' => '123 Admin Street, TP.HCM',
                'latitude' => 10.7719,
                'longitude' => 106.6983,
            ]);

            \App\Models\User::create([
                'name' => 'Personal User',
                'email' => 'user@gmail.com',
                'email_verified_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'personal',
                'phone' => '0912345678',
                'address' => '456 User Street, TP.HCM',
                'latitude' => 10.7725,
                'longitude' => 106.6990,
            ]);

            \App\Models\User::create([
                'name' => 'Charity Organization',
                'email' => 'charity@gmail.com',
                'email_verified_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'charity',
                'phone' => '0987654321',
                'address' => '789 Charity Avenue, TP.HCM',
                'latitude' => 10.7700,
                'longitude' => 106.6950,
            ]);
        }

        // Gọi thêm seeder của bài đăng thực phẩm
        $this->call([
            FoodPostSeeder::class,
        ]);
    }

}
