<?php

namespace Database\Seeders;

use App\Models\StoreInfo;
use Illuminate\Database\Seeder;

class StoreInfoSeeder extends Seeder
{
    public function run(): void
    {
        StoreInfo::create([
            'store_name' => 'Qammaris Perfumes',
            'tagline' => 'Your Trusted Middle Eastern Fragrance Specialist',
            'whatsapp_number' => '6282271636339',
            'phone_number' => '082271636339',
            'email' => 'info@qammarisperfumes.com',
            'address' => "Jl. Contoh Alamat No. 123\nKota, Provinsi 12345\nIndonesia",
            'google_maps_embed' => 'https://maps.app.goo.gl/STkbgivkRoWU9iZJ6',
            'instagram_url' => 'https://instagram.com/qammarisperfumes',
            'facebook_url' => null,
            'tokopedia_url' => 'https://tokopedia.com/qammarisperfumes',
            'shopee_url' => 'https://shopee.co.id/qammarisperfumes',
            'tiktokshop_url' => null,
            'about_description' => "Qammaris Perfumes adalah distributor resmi parfum original dari Timur Tengah. Kami menyediakan berbagai brand ternama seperti Afnan, Zimaya, Lattafa, dan masih banyak lagi.\n\nKami berkomitmen menyediakan produk 100% original dengan harga terbaik. Setiap pembelian dilengkapi dengan garansi keaslian produk.",
            'hero_title' => 'Explore Premium Middle Eastern Fragrances',
            'hero_subtitle' => 'Koleksi parfum otentik dari brand ternama Timur Tengah dengan harga terbaik',
        ]);
    }
}