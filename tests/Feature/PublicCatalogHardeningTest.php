<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCatalogHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->brand = Brand::create(['name' => 'Maison Journey', 'is_active' => true]);
        $this->category = Category::create(['name' => 'EDP Journey', 'is_active' => true]);
    }

    public function test_public_layout_and_catalog_media_expose_accessible_stable_markup(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createProduct('Journey Product '.$index, 100000 + $index);
        }

        $response = $this->get(route('products.index'));
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('Lewati ke konten utama')
            ->assertSee('id="main-content" tabindex="-1"', false)
            ->assertSee('Koleksi Parfum')
            ->assertSee('Parfum pilihan Qammaris')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('href="#how-to-order"', false);

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertSame(4, substr_count($html, 'loading="lazy"'));
        $this->assertSame(5, substr_count($html, 'width="480" height="640"'));
    }

    public function test_catalog_query_count_does_not_grow_with_the_page_size(): void
    {
        $this->createProduct('Query Product 01', 100001);
        $smallQueryCount = $this->catalogSelectQueryCount();

        foreach (range(2, 24) as $index) {
            $this->createProduct(sprintf('Query Product %02d', $index), 100000 + $index);
        }

        $largeQueryCount = $this->catalogSelectQueryCount();

        $this->assertLessThanOrEqual(
            $smallQueryCount + 1,
            $largeQueryCount,
            "Catalog SELECT queries grew from {$smallQueryCount} to {$largeQueryCount}.",
        );
    }

    public function test_customer_journey_preserves_discovery_context_and_uses_current_inquiry_data(): void
    {
        [$product, $offer] = $this->createProduct('Journey Signature', 375000);
        $query = [
            'search' => 'Journey Signature',
            'brand' => [$this->brand->id],
            'category' => $this->category->id,
            'availability' => Product::AVAILABILITY_UNKNOWN,
            'sort' => 'price_low',
        ];
        $detailUrl = route('products.show', array_merge(['product' => $product->slug], $query));

        $this->get(route('products.index', $query))
            ->assertOk()
            ->assertSee($detailUrl);

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee(route('products.index', $query))
            ->assertSee('Tambah ke daftar inquiry');

        $this->postJson(route('cart.add'), [
            'variant_id' => $offer->id,
            'quantity' => 2,
        ])->assertOk();

        $product->update(['name' => 'Journey Signature Current']);
        $offer->update(['volume' => 75, 'price' => 425000]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Journey Signature Current')
            ->assertSee('75 ml')
            ->assertSee('Rp 850.000')
            ->assertDontSee('Rp 750.000');

        $checkout = $this->post(route('cart.checkout'), [
            'customer_note' => 'Konfirmasi untuk akhir pekan.',
        ]);
        $checkout->assertRedirect();

        parse_str((string) parse_url($checkout->headers->get('Location'), PHP_URL_QUERY), $whatsappQuery);
        $message = $whatsappQuery['text'] ?? '';

        $this->assertStringContainsString('Maison Journey - Journey Signature Current', $message);
        $this->assertStringContainsString('Ukuran: 75 ml', $message);
        $this->assertStringContainsString('Jumlah yang diminati: 2', $message);
        $this->assertStringContainsString('Harga saat ini: Rp 425.000 / item', $message);
        $this->assertStringContainsString('Estimasi nilai produk: Rp 850.000', $message);
        $this->assertStringContainsString('Catatan: Konfirmasi untuk akhir pekan.', $message);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function createProduct(string $name, int $price): array
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => 'Deskripsi '.$name,
            'base_price' => $price,
            'gender' => 'Unisex',
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        $offer = ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => $price,
            'stock' => 0,
            'is_active' => true,
        ]);

        return [$product, $offer];
    }

    private function catalogSelectQueryCount(): int
    {
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('products.index'))->assertOk();

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select'))
            ->count();

        DB::disableQueryLog();

        return $queries;
    }
}
