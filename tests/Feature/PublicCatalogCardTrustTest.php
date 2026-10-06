<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogCardTrustTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->brand = Brand::create([
            'name' => 'Maison Test',
            'is_active' => true,
        ]);
        $this->category = Category::create([
            'name' => 'Eau de Parfum',
            'is_active' => true,
        ]);
    }

    public function test_card_uses_only_active_offer_for_size_and_price(): void
    {
        $product = $this->createProduct('Trusted Offer', [
            'base_price' => 987654,
            'compare_at_price' => 999999,
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHour(),
        ]);
        $this->createOffer($product, volume: 75, price: 425000, active: true);

        $response = $this->get(route('products.index'));

        $response->assertOk()
            ->assertSee('75 ml')
            ->assertSee('Rp 425.000')
            ->assertSee('Tersedia saat diperiksa')
            ->assertDontSee('Rp 987.654')
            ->assertDontSee('Rp 999.999');

        $this->assertSame(
            1,
            substr_count($response->getContent(), 'href="'.route('products.show', $product->slug).'"'),
        );
    }

    public function test_card_exposes_honest_missing_data_and_local_placeholder(): void
    {
        $product = $this->createProduct('Incomplete Product', [
            'base_price' => 765432,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);

        $response = $this->get(route('products.index'));

        $response->assertOk()
            ->assertSee('Data sedang dilengkapi')
            ->assertSee('Foto sedang dilengkapi')
            ->assertSee('Konfirmasi stok')
            ->assertSee(asset('images/product-placeholder.svg'), false)
            ->assertDontSee('Rp 765.432')
            ->assertDontSee('placehold.co', false);

        $this->assertFileExists(public_path(ltrim(ProductImage::PLACEHOLDER_URL, '/')));
        $this->assertSame('/images/product-placeholder.svg', ProductImage::PLACEHOLDER_URL);
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'href="'.route('products.show', $product->slug).'"'),
        );
    }

    public function test_card_maps_effective_availability_and_keeps_sold_out_discoverable(): void
    {
        $fresh = $this->createProduct('Fresh Product', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(35),
        ]);
        $stale = $this->createProduct('Stale Product', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(37),
        ]);
        $soldOut = $this->createProduct('Sold Out Product', [
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now()->subWeek(),
        ]);

        foreach ([$fresh, $stale, $soldOut] as $product) {
            $this->createOffer($product, volume: 100, price: 250000);
        }

        $response = $this->get(route('products.index'));

        $response->assertOk()
            ->assertSee('data-effective-availability="available"', false)
            ->assertSee('data-effective-availability="unknown"', false)
            ->assertSee('data-effective-availability="sold_out"', false)
            ->assertSee('Tersedia saat diperiksa')
            ->assertSee('Konfirmasi stok')
            ->assertSee('Sold out')
            ->assertSee(route('products.show', $soldOut->slug), false);
    }

    public function test_best_seller_and_loading_hints_are_presented_without_duplicate_card_actions(): void
    {
        $products = collect(range(1, 5))->map(function (int $index): Product {
            $product = $this->createProduct('Catalog Card '.$index, [
                'is_best_seller' => $index === 1,
                'published_at' => now()->subMinutes($index),
            ]);
            $this->createOffer($product, volume: 50, price: 100000 + $index);

            return $product;
        });

        $response = $this->get(route('products.index'));
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('Terlaris')
            ->assertSee('fetchpriority="high"', false)
            ->assertSee('loading="lazy"', false);

        $this->assertSame(1, substr_count($html, '>Terlaris</span>'));
        foreach ($products as $product) {
            $this->assertSame(1, substr_count($html, 'href="'.route('products.show', $product->slug).'"'));
        }
    }

    private function createProduct(string $name, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => 'Deskripsi '.$name,
            'base_price' => 100000,
            'gender' => 'Unisex',
            'is_best_seller' => false,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'view_count' => 0,
        ], $overrides));
    }

    private function createOffer(Product $product, int $volume, int $price, bool $active = true): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'volume' => $volume,
            'price' => $price,
            'sku' => null,
            'stock' => 0,
            'is_active' => $active,
        ]);
    }
}
