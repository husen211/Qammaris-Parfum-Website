<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicProductDetailTrustTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        config(['media.product_disk' => 'public']);

        $this->brand = Brand::create([
            'name' => 'Maison Detail',
            'is_active' => true,
        ]);
        $this->category = Category::create([
            'name' => 'Eau de Parfum',
            'is_active' => true,
        ]);
    }

    public function test_detail_presents_one_active_offer_and_effective_availability_without_variant_selector(): void
    {
        $product = $this->createProduct('Trusted Detail', [
            'base_price' => 999999,
            'compare_at_price' => 500000,
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHour(),
        ]);
        $offer = $this->createOffer($product, volume: 75, price: 425000, stock: 4);

        $response = $this->get(route('products.show', [
            'product' => $product,
            'search' => 'Trusted',
            'sort' => 'popular',
            'page' => 2,
            'ignored' => 'drop-me',
        ]));

        $response->assertOk()
            ->assertSee('75 ml')
            ->assertSee('Rp 425.000')
            ->assertSee('Rp 500.000')
            ->assertSee('Tersedia saat diperiksa')
            ->assertSee('Pemeriksaan terakhir')
            ->assertSee('data-detail-availability="available"', false)
            ->assertSee('data-variant-id="'.$offer->id.'"', false)
            ->assertSee('Kembali ke hasil')
            ->assertSee(route('products.index', [
                'search' => 'Trusted',
                'sort' => 'popular',
                'page' => 2,
            ]))
            ->assertDontSee('Rp 999.999')
            ->assertDontSee('Select Size')
            ->assertDontSee('In Stock')
            ->assertDontSee('ignored');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_detail_without_offer_or_image_uses_neutral_local_states_and_disables_cart_action(): void
    {
        $product = $this->createProduct('Incomplete Detail', [
            'base_price' => 765432,
            'description' => null,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk()
            ->assertSee('Data sedang dilengkapi')
            ->assertSee('Ukuran dan harga belum tersedia untuk ditampilkan.')
            ->assertSee('Data produk belum lengkap')
            ->assertSee('Foto sedang dilengkapi')
            ->assertSee('Deskripsi sedang dilengkapi.')
            ->assertSee('Informasi notes sedang dilengkapi.')
            ->assertSee('Konfirmasi stok')
            ->assertSee(asset('images/product-placeholder.svg'), false)
            ->assertDontSee('Rp 765.432')
            ->assertDontSee('<button type="button" data-add-to-cart', false)
            ->assertDontSee('placehold.co', false);
    }

    public function test_detail_maps_stale_available_to_unknown_and_keeps_sold_out_discoverable(): void
    {
        $stale = $this->createProduct('Stale Detail', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(37),
        ]);
        $soldOut = $this->createProduct('Sold Out Detail', [
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now()->subDay(),
        ]);
        $this->createOffer($stale);
        $this->createOffer($soldOut);

        $this->get(route('products.show', $stale))
            ->assertOk()
            ->assertSee('data-detail-availability="unknown"', false)
            ->assertSee('Konfirmasi stok')
            ->assertDontSee('Pemeriksaan terakhir');

        $this->get(route('products.show', $soldOut))
            ->assertOk()
            ->assertSee('data-detail-availability="sold_out"', false)
            ->assertSee('Sold out')
            ->assertSee('Produk sold out')
            ->assertSee('Pesan via WhatsApp')
            ->assertDontSee('<button type="button" data-add-to-cart', false);
    }

    public function test_gallery_renders_each_image_once_as_a_thumbnail_with_accessible_selected_state(): void
    {
        $product = $this->createProduct('Gallery Detail');
        $this->createOffer($product);
        Storage::disk('public')->put('products/primary.jpg', 'primary');
        Storage::disk('public')->put('products/second.jpg', 'second');

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/second.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/primary.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('products.show', $product));
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('aria-label="Pilihan foto produk"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('justify-center', false);

        $this->assertSame(2, substr_count($html, '<button type="button" data-gallery-thumbnail'));
        $this->assertSame(1, substr_count($html, 'aria-pressed="true"'));
        $this->assertMatchesRegularExpression('/id="mainImage"[\s\S]*?class="(?![^"]*p-8)[^"]*object-contain[^"]*"/', $html);
    }

    public function test_mobile_detail_places_a_compact_gallery_before_the_product_summary(): void
    {
        $product = $this->createProduct('Media First Detail');
        $this->createOffer($product);

        $response = $this->get(route('products.show', $product));
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('class="order-1 min-w-0" aria-label="Galeri Media First Detail" data-product-gallery', false)
            ->assertSee('max-w-[15rem]', false)
            ->assertSee('lg:max-w-sm', false)
            ->assertSee('aspect-[4/5]', false)
            ->assertSee('object-contain', false)
            ->assertSee('p-8', false)
            ->assertSee('lg:p-12', false)
            ->assertDontSee('lg:object-cover', false)
            ->assertSee('class="order-2 min-w-0 lg:sticky lg:top-28" aria-labelledby="product-title"', false);

        $galleryPosition = strpos($html, '<section class="order-1 min-w-0"');
        $summaryPosition = strpos($html, '<section class="order-2 min-w-0 lg:sticky lg:top-28"');

        $this->assertNotFalse($galleryPosition);
        $this->assertNotFalse($summaryPosition);
        $this->assertLessThan($summaryPosition, $galleryPosition);
    }

    public function test_notes_render_only_nonempty_groups_and_escape_imported_content(): void
    {
        $product = $this->createProduct('Notes Detail', [
            'fragrance_notes' => [
                'top' => ['Bergamot', '<script>notesAttack()</script>'],
                'middle' => [],
                'base' => ['Musk'],
            ],
        ]);
        $this->createOffer($product);

        $response = $this->get(route('products.show', $product));

        $response->assertOk()
            ->assertSee('Top notes')
            ->assertSee('Base notes')
            ->assertDontSee('Middle notes')
            ->assertSee('&lt;script&gt;notesAttack()&lt;/script&gt;', false)
            ->assertDontSee('<script>notesAttack()</script>', false);
    }

    public function test_related_products_reuse_card_trust_layer_and_preserve_detail_context(): void
    {
        $product = $this->createProduct('Main Detail');
        $this->createOffer($product, price: 300000);
        $related = $this->createProduct('Related Detail', ['base_price' => 888888]);
        $this->createOffer($related, volume: 50, price: 225000);

        $response = $this->get(route('products.show', [
            'product' => $product,
            'search' => 'Detail',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('Produk terkait')
            ->assertSee('Related Detail')
            ->assertSee('50 ml')
            ->assertSee('Rp 225.000')
            ->assertSee(route('products.show', [
                'product' => $related,
                'search' => 'Detail',
                'page' => 2,
            ]))
            ->assertDontSee('Rp 888.888')
            ->assertDontSee('placehold.co', false);
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

    private function createOffer(
        Product $product,
        int $volume = 100,
        int $price = 250000,
        int $stock = 1,
    ): ProductVariant {
        return ProductVariant::create([
            'product_id' => $product->id,
            'volume' => $volume,
            'price' => $price,
            'sku' => null,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }
}
