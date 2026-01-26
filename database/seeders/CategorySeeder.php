<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Eau de Parfum (EDP)',
                'description' => 'Konsentrasi parfum 15-20%, tahan 6-8 jam',
            ],
            [
                'name' => 'Eau de Toilette (EDT)',
                'description' => 'Konsentrasi parfum 5-15%, tahan 3-5 jam',
            ],
            [
                'name' => 'Parfum Oil',
                'description' => 'Konsentrasi tinggi tanpa alkohol, tahan sangat lama',
            ],
            [
                'name' => 'Extrait de Parfum',
                'description' => 'Konsentrasi tertinggi 20-30%, sangat eksklusif',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}