<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User; // Import Model User biar rapi

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Jalankan Seeder Lain Dulu (Pastikan file-file ini ada & tidak error)
        // Jika salah satu file ini belum ada, beri komentar (//) dulu biar ga error
        $this->call([
            BrandSeeder::class,
            CategorySeeder::class,
            StoreInfoSeeder::class,
            ProductSeeder::class,
            BlogPostSeeder::class,
        ]);

        // 2. Buat Akun Admin (Safe Method)
        // updateOrCreate: Cek apakah email ini ada? Kalau ada update, kalau ga ada create.
        User::updateOrCreate(
            ['email' => 'admin@qammaris.com'], // Kunci pencarian (Search key)
            [
                'name' => 'Admin Qammaris',
                'role' => 'admin', // Pastikan kolom 'role' sudah dibuat lewat migration sebelumnya
                'password' => bcrypt('password123'), // Password Admin
                'email_verified_at' => now(),
            ]
        );
        
        // 3. Buat Akun Customer Dummy (Opsional - buat ngetes login user biasa)
        User::updateOrCreate(
            ['email' => 'customer@gmail.com'],
            [
                'name' => 'Customer Test',
                'role' => 'customer',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );
    }
}