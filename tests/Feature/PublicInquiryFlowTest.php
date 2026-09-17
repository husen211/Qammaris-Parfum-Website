<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInquiryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_detail_builds_contextual_stock_and_restock_links(): void
    {
        [$product, $offer] = $this->createCatalogItem([
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);

        $response = $this->get(route('products.show', $product));
        $message = $this->firstWhatsappMessage($response->getContent());

        $response->assertOk()
            ->assertSee('Tambah ke daftar inquiry')
            ->assertSee('Tanya stok via WhatsApp');
        $this->assertStringContainsString($product->brand->name.' - '.$product->name, $message);
        $this->assertStringContainsString('Ukuran: '.$offer->volume.' ml', $message);
        $this->assertStringContainsString('Harga saat ini: Rp 250.000', $message);
        $this->assertStringContainsString('Status website: Konfirmasi stok', $message);
        $this->assertStringContainsString(route('products.show', $product), $message);
        $this->assertStringContainsString('Intent: konfirmasi stok', $message);

        $product->update([
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now(),
        ]);

        $soldOutResponse = $this->get(route('products.show', $product));
        $soldOutMessage = $this->firstWhatsappMessage($soldOutResponse->getContent());

        $soldOutResponse->assertOk()
            ->assertSee('Tanya restock via WhatsApp')
            ->assertDontSee('Tambah ke daftar inquiry')
            ->assertDontSee('data-variant-id=', false);
        $this->assertStringContainsString('Status website: Sold out', $soldOutMessage);
        $this->assertStringContainsString('Intent: informasi restock', $soldOutMessage);
    }

    public function test_zero_variant_stock_does_not_block_an_inquiry_add(): void
    {
        [, $offer] = $this->createCatalogItem([], ['stock' => 0]);

        $this->postJson(route('cart.add'), [
            'variant_id' => $offer->id,
            'quantity' => 2,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Produk ditambahkan ke daftar inquiry.');

        $this->assertSame(2, session("cart.{$offer->id}.quantity"));
    }

    public function test_sold_out_product_cannot_be_added_as_a_regular_inquiry(): void
    {
        [, $offer] = $this->createCatalogItem([
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now(),
        ]);

        $this->postJson(route('cart.add'), [
            'variant_id' => $offer->id,
            'quantity' => 1,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertEmpty(session('cart', []));
    }

    public function test_inquiry_page_and_drawer_data_use_current_values_and_local_placeholder(): void
    {
        [$product, $offer] = $this->createCatalogItem();
        $sessionItem = $this->sessionItem($product, $offer);
        $product->brand->update(['name' => 'Brand Current']);
        $product->update(['name' => 'Product Current']);
        $offer->update(['volume' => 75, 'price' => 175000]);

        $page = $this->withSession(['cart' => [$offer->id => $sessionItem]])
            ->get(route('cart.index'));

        $page->assertOk()
            ->assertSee('Daftar Inquiry')
            ->assertSee('Brand Current')
            ->assertSee('Product Current')
            ->assertSee('75 ml')
            ->assertSee('Rp 175.000')
            ->assertSee(asset('images/product-placeholder.svg'), false)
            ->assertDontSee('Stale Product')
            ->assertDontSee('Keranjang Belanja')
            ->assertDontSee('Alamat Pengiriman')
            ->assertDontSee('Checkout aman')
            ->assertDontSee('placehold.co', false);

        $data = $this->withSession(['cart' => [$offer->id => $sessionItem]])
            ->getJson(route('cart.data'));

        $data->assertOk()
            ->assertJsonPath('items.0.brand_name', 'Brand Current')
            ->assertJsonPath('items.0.product_name', 'Product Current')
            ->assertJsonPath('items.0.volume', 75)
            ->assertJsonPath('items.0.formatted_price', 'Rp 175.000')
            ->assertJsonPath('items.0.availability_label', 'Konfirmasi stok')
            ->assertJsonPath('items.0.image', asset('images/product-placeholder.svg'));
    }

    public function test_stale_session_list_is_not_partially_rendered_or_sent(): void
    {
        [$product, $offer] = $this->createCatalogItem();
        $sessionItem = $this->sessionItem($product, $offer);
        $product->update([
            'publication_status' => Product::PUBLICATION_ARCHIVED,
            'is_active' => false,
        ]);

        $this->withSession(['cart' => [$offer->id => $sessionItem]])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Daftar perlu ditinjau')
            ->assertDontSee('Tanyakan via WhatsApp');

        $this->withSession(['cart' => [$offer->id => $sessionItem]])
            ->getJson(route('cart.data'))
            ->assertStatus(409)
            ->assertJsonPath('items', []);
    }

    private function createCatalogItem(array $productOverrides = [], array $offerOverrides = []): array
    {
        $brand = Brand::create(['name' => 'Maison Inquiry', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP', 'is_active' => true]);
        $product = Product::create(array_merge([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Inquiry Product',
            'description' => 'Deskripsi inquiry.',
            'base_price' => 250000,
            'gender' => 'Unisex',
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ], $productOverrides));
        $offer = ProductVariant::create(array_merge([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 250000,
            'stock' => 10,
            'is_active' => true,
        ], $offerOverrides));

        return [$product->load('brand'), $offer];
    }

    private function sessionItem(Product $product, ProductVariant $offer): array
    {
        return [
            'variant_id' => $offer->id,
            'product_id' => $product->id,
            'product_name' => 'Stale Product',
            'slug' => 'stale-product',
            'brand_name' => 'Stale Brand',
            'volume' => 1,
            'price' => 1,
            'quantity' => 1,
            'image' => 'https://example.test/stale.png',
        ];
    }

    private function firstWhatsappMessage(string $html): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match('/href="(https:\/\/wa\.me\/[^\"]+\?text=[^\"]+)"/', $decoded, $matches);
        $this->assertArrayHasKey(1, $matches);
        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);

        return $query['text'] ?? '';
    }
}
