<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'brand' => 'Afnan',
                'category' => 'Eau de Parfum (EDP)',
                'name' => '9PM',
                'description' => 'Parfum mewah dengan aroma woody spicy yang maskulin. Cocok untuk malam hari dan acara formal. Notes: Bergamot, Apple, Cinnamon, Lavender, Orange Blossom.',
                'base_price' => 250000,
                'gender' => 'Pria',
                'is_best_seller' => true,
                'fragrance_notes' => [
                    'top' => ['Bergamot', 'Apple', 'Cinnamon'],
                    'middle' => ['Lavender', 'Orange Blossom'],
                    'base' => ['Vanilla', 'Tonka Bean', 'Amber']
                ],
                'variants' => [
                    ['volume' => 50, 'price' => 250000, 'stock' => 15],
                    ['volume' => 100, 'price' => 425000, 'stock' => 10],
                ],
            ],
            [
                'brand' => 'Zimaya',
                'category' => 'Extrait de Parfum',
                'name' => 'Sharaf',
                'description' => 'Parfum eksklusif dengan konsentrasi tinggi. Aroma oriental woody yang sophisticated dan elegan.',
                'base_price' => 350000,
                'gender' => 'Pria',
                'is_best_seller' => true,
                'fragrance_notes' => [
                    'top' => ['Grapefruit', 'Mint', 'Pink Pepper'],
                    'middle' => ['Ginger', 'Nutmeg', 'Jasmine'],
                    'base' => ['Incense', 'Patchouli', 'Cedar', 'Labdanum']
                ],
                'variants' => [
                    ['volume' => 100, 'price' => 550000, 'stock' => 8],
                ],
            ],
            [
                'brand' => 'Lattafa',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Asad',
                'description' => 'Parfum segar dan maskulin dengan aroma aquatic woody. Cocok untuk sehari-hari.',
                'base_price' => 180000,
                'gender' => 'Pria',
                'is_best_seller' => false,
                'fragrance_notes' => [
                    'top' => ['Lemon', 'Bergamot', 'Aquatic Notes'],
                    'middle' => ['Lavender', 'Geranium'],
                    'base' => ['Vetiver', 'Cedar', 'Tonka Bean']
                ],
                'variants' => [
                    ['volume' => 50, 'price' => 180000, 'stock' => 20],
                    ['volume' => 100, 'price' => 320000, 'stock' => 12],
                ],
            ],
            [
                'brand' => 'Fragrance World',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Intenso',
                'description' => 'Inspired by luxury designer fragrance. Aroma oriental spicy yang intens dan tahan lama.',
                'base_price' => 150000,
                'gender' => 'Pria',
                'is_best_seller' => true,
                'fragrance_notes' => [
                    'top' => ['Black Pepper', 'Tobacco'],
                    'middle' => ['Iris', 'Saffron'],
                    'base' => ['Amber', 'Patchouli', 'Vanilla']
                ],
                'variants' => [
                    ['volume' => 80, 'price' => 280000, 'stock' => 18],
                ],
            ],
            [
                'brand' => 'Afnan',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Supremacy Silver',
                'description' => 'Parfum fresh dan clean dengan aroma citrus woody. Sangat populer untuk daily wear.',
                'base_price' => 220000,
                'gender' => 'Pria',
                'is_best_seller' => true,
                'fragrance_notes' => [
                    'top' => ['Lemon', 'Pink Pepper', 'Green Apple'],
                    'middle' => ['Ginger', 'Mint', 'Nutmeg'],
                    'base' => ['Incense', 'Woody Notes', 'Amber']
                ],
                'variants' => [
                    ['volume' => 50, 'price' => 220000, 'stock' => 25],
                    ['volume' => 100, 'price' => 380000, 'stock' => 15],
                ],
            ],
            [
                'brand' => 'Lattafa',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Yara',
                'description' => 'Parfum manis dan feminin dengan aroma gourmand floral. Best seller untuk wanita.',
                'base_price' => 200000,
                'gender' => 'Wanita',
                'is_best_seller' => true,
                'fragrance_notes' => [
                    'top' => ['Heliotrope', 'Orchid', 'Tangerine'],
                    'middle' => ['Gourmand', 'Tropical Fruits'],
                    'base' => ['Vanilla', 'Musk', 'Sandalwood']
                ],
                'variants' => [
                    ['volume' => 50, 'price' => 200000, 'stock' => 30],
                    ['volume' => 100, 'price' => 350000, 'stock' => 20],
                ],
            ],
            [
                'brand' => 'Armaf',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Club De Nuit Intense',
                'description' => 'Salah satu parfum terpopuler dari Armaf. Aroma woody spicy yang powerful dan sophisticated.',
                'base_price' => 280000,
                'gender' => 'Pria',
                'is_best_seller' => true,
                'fragrance_notes' => [
                    'top' => ['Pineapple', 'Blackcurrant', 'Apple', 'Bergamot'],
                    'middle' => ['Birch', 'Patchouli', 'Jasmine', 'Rose'],
                    'base' => ['Musk', 'Oakmoss', 'Ambergris', 'Vanilla']
                ],
                'variants' => [
                    ['volume' => 105, 'price' => 450000, 'stock' => 12],
                ],
            ],
            [
                'brand' => 'Rasasi',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Hawas',
                'description' => 'Parfum aquatic fruity yang fresh dan energetic. Cocok untuk cuaca tropis Indonesia.',
                'base_price' => 240000,
                'gender' => 'Pria',
                'is_best_seller' => false,
                'fragrance_notes' => [
                    'top' => ['Lemon', 'Apple', 'Cinnamon', 'Bergamot'],
                    'middle' => ['Watery Notes', 'Plum', 'Cardamom', 'Orange Blossom'],
                    'base' => ['Musk', 'Driftwood', 'Patchouli', 'Ambergris']
                ],
                'variants' => [
                    ['volume' => 50, 'price' => 240000, 'stock' => 18],
                    ['volume' => 100, 'price' => 420000, 'stock' => 10],
                ],
            ],
            [
                'brand' => 'Zimaya',
                'category' => 'Extrait de Parfum',
                'name' => 'Maahir Purple',
                'description' => 'Parfum unisex dengan aroma floral fruity yang elegan dan misterius.',
                'base_price' => 320000,
                'gender' => 'Unisex',
                'is_best_seller' => false,
                'fragrance_notes' => [
                    'top' => ['Saffron', 'Nutmeg'],
                    'middle' => ['Lavender', 'Orange Blossom'],
                    'base' => ['Amber', 'Woody Notes', 'Musk']
                ],
                'variants' => [
                    ['volume' => 80, 'price' => 480000, 'stock' => 7],
                ],
            ],
            [
                'brand' => 'Fragrance World',
                'category' => 'Eau de Parfum (EDP)',
                'name' => 'Ombre Platine',
                'description' => 'Inspired by luxury perfume. Aroma fresh citrus woody yang sophisticated.',
                'base_price' => 160000,
                'gender' => 'Pria',
                'is_best_seller' => false,
                'fragrance_notes' => [
                    'top' => ['Grapefruit', 'Lemon', 'Mint'],
                    'middle' => ['Ginger', 'Nutmeg'],
                    'base' => ['Cedar', 'Vetiver', 'Patchouli', 'Frankincense']
                ],
                'variants' => [
                    ['volume' => 80, 'price' => 260000, 'stock' => 15],
                ],
            ],
        ];

        foreach ($products as $productData) {
            // Get brand and category
            $brand = Brand::where('name', $productData['brand'])->first();
            $category = Category::where('name', $productData['category'])->first();

            // Create product
            $product = Product::create([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'name' => $productData['name'],
                'description' => $productData['description'],
                'base_price' => $productData['base_price'],
                'gender' => $productData['gender'],
                'is_best_seller' => $productData['is_best_seller'],
                'fragrance_notes' => $productData['fragrance_notes'],
                'meta_description' => substr($productData['description'], 0, 160),
            ]);

            // Create variants
            foreach ($productData['variants'] as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'volume' => $variantData['volume'],
                    'price' => $variantData['price'],
                    'sku' => strtoupper(str_replace(' ', '-', $brand->name . '-' . $product->name . '-' . $variantData['volume'] . 'ML')),
                    'stock' => $variantData['stock'],
                ]);
            }

            // Create dummy image (placeholder)
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => 'products/placeholder.jpg', // Nanti akan diganti dengan upload asli
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }
    }
}