<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            [
                'name' => 'Afnan',
                'description' => 'Brand parfum premium dari UAE yang terkenal dengan koleksi oriental dan woody fragrances.',
                'is_active' => true,
            ],
            [
                'name' => 'Zimaya',
                'description' => 'Parfum mewah dari Timur Tengah dengan konsentrasi tinggi dan daya tahan luar biasa.',
                'is_active' => true,
            ],
            [
                'name' => 'Fragrance World',
                'description' => 'Koleksi parfum inspired by designer fragrances dengan harga terjangkau.',
                'is_active' => true,
            ],
            [
                'name' => 'Lattafa',
                'description' => 'Salah satu brand terpopuler dari Dubai dengan beragam pilihan aroma oriental.',
                'is_active' => true,
            ],
            [
                'name' => 'Armaf',
                'description' => 'Brand parfum UAE yang menawarkan luxury fragrances dengan harga kompetitif.',
                'is_active' => true,
            ],
            [
                'name' => 'Rasasi',
                'description' => 'Parfum heritage dari Dubai dengan tradisi lebih dari 35 tahun.',
                'is_active' => true,
            ],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}