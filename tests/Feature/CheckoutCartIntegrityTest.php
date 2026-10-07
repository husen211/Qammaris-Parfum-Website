<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\InquiryWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCartIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_checkout_uses_current_catalog_and_required_recipient_without_persisting_order(): void
    {
        [$product, $variant] = $this->createCatalogItem();
        $product->brand->update(['name' => 'Current Brand']);
        $product->update(['name' => 'Current Product']);
        $variant->update(['volume' => 75, 'price' => 150000]);
        $this->review($variant);
        $response = $this->post(route('cart.checkout'), $this->customerData());
        $this->assertStringStartsWith('https://wa.me/', $response->headers->get('Location'));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $message = $query['text'];
        foreach (['Current Brand - Current Product', 'Ukuran: 75 ml', 'Jumlah: 2', 'Harga: Rp 150.000 / item',
            'Subtotal: Rp 300.000', 'Nama: Test Recipient', 'No. HP: 081234567890',
            'Alamat: Alamat sintetis untuk pengujian checkout', 'Kode pos: 12345', 'Catatan: Catatan sintetis.'] as $text) {
            $this->assertStringContainsString($text, $message);
        }
        foreach (['Stale Product', 'inquiry', 'Mohon konfirmasi', 'sudah dibayar', 'invoice'] as $text) {
            $this->assertStringNotContainsString($text, $message);
        }
        $response->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame(2, session("cart.{$variant->id}.quantity"));
        $this->assertFalse(session()->has('customer_name'));
        $this->assertFalse(session()->has('_old_input.customer_name'));
        $this->assertDatabaseCount('products', 1);
    }

    public function test_missing_and_malformed_recipient_is_rejected_and_can_be_corrected(): void
    {
        [, $variant] = $this->createCatalogItem();
        $this->review($variant);
        $this->from(route('cart.checkout.show'))->post(route('cart.checkout'), ['checkout_quote' => session('checkout_quote')])
            ->assertSessionHasErrors(['customer_name', 'customer_phone', 'customer_address']);
        $this->post(route('cart.checkout'), array_merge($this->customerData(), [
            'customer_phone' => 'not a phone', 'customer_address' => 'short', 'customer_postcode' => 'abcd', 'customer_note' => str_repeat('x', 301),
        ]))->assertSessionHasErrors(['customer_phone', 'customer_address', 'customer_postcode', 'customer_note']);
    }

    public function test_a_price_change_after_review_requires_a_new_review(): void
    {
        [, $variant] = $this->createCatalogItem();
        $this->review($variant);
        $oldData = $this->customerData();
        $variant->update(['price' => 175000]);
        $this->post(route('cart.checkout'), $oldData)->assertRedirect(route('cart.checkout.show'))->assertSessionHas('error');
        $this->get(route('cart.checkout.show'))->assertOk()->assertSee('Rp 350.000')->assertSee('Test Recipient');
        $this->post(route('cart.checkout'), $this->customerData())->assertRedirect();
    }

    public function test_stale_quantities_and_cross_session_review_are_rejected(): void
    {
        [, $variant] = $this->createCatalogItem();
        $this->review($variant);
        $data = $this->customerData();
        session(["cart.{$variant->id}.quantity" => 3]);
        $this->post(route('cart.checkout'), $data)->assertRedirect(route('cart.checkout.show'))->assertSessionHas('error');
        $this->post(route('cart.checkout'), array_merge($data, ['checkout_quote' => str_repeat('a', 64)]))
            ->assertRedirect(route('cart.checkout.show'))->assertSessionHas('error');
    }

    public function test_checkout_rejects_unpublished_inactive_sold_out_and_unknown_products(): void
    {
        foreach ([['publication_status' => 'draft'], ['is_active' => false], ['availability_status' => 'sold_out'], ['availability_status' => 'unknown']] as $changes) {
            [$product, $variant] = $this->createCatalogItem();
            $this->review($variant);
            $data = $this->customerData();
            $product->update($changes);
            $this->post(route('cart.checkout'), $data)->assertRedirect(route('cart.index'))->assertSessionHas('error');
        }
    }

    public function test_invalid_quantity_and_empty_cart_cannot_checkout(): void
    {
        [, $variant] = $this->createCatalogItem();
        $item = $this->staleSessionItem($variant);
        $item['quantity'] = 'not-an-integer';
        $this->withSession(['cart' => [$variant->id => $item]])->get(route('cart.checkout.show'))->assertRedirect(route('cart.index'));
        $this->withSession(['cart' => []])->get(route('cart.checkout.show'))->assertRedirect(route('cart.index'));
    }

    public function test_unknown_or_zero_price_cannot_enter_order_cart(): void
    {
        [$product, $variant] = $this->createCatalogItem();
        $product->update(['availability_status' => 'unknown']);
        $this->postJson(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertUnprocessable();
        $product->update(['availability_status' => 'available']);
        $variant->update(['price' => 0]);
        $this->postJson(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertUnprocessable();
    }

    public function test_whatsapp_builder_rejects_invalid_destinations(): void
    {
        $builder = app(InquiryWhatsApp::class);
        $this->assertFalse($builder->hasValidNumber('invalid'));
        $this->assertNull($builder->orderUrl('invalid', [['price' => 100000, 'quantity' => 1, 'brand_name' => 'Test',
            'product_name' => 'Test', 'volume' => 50, 'product_url' => 'https://example.test/product']], $this->customerData()));
    }

    public function test_untrusted_old_input_is_escaped_and_phone_format_is_normalized(): void
    {
        [, $variant] = $this->createCatalogItem();
        $this->review($variant);
        $this->withSession(['_old_input' => ['customer_name' => '<script>alert(1)</script>']])
            ->get(route('cart.checkout.show'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $response = $this->post(route('cart.checkout'), array_merge($this->customerData(), ['customer_phone' => '+62 (812) 3456-7890']));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('No. HP: +6281234567890', $query['text']);
    }

    private function review(ProductVariant $variant): void
    {
        $this->withSession(['cart' => [$variant->id => $this->staleSessionItem($variant)]])
            ->get(route('cart.checkout.show'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    }

    private function createCatalogItem(): array
    {
        $brand = Brand::create([
            'name' => 'Original Brand',
            'is_active' => true,
        ]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Original Product',
            'description' => 'Description.',
            'base_price' => 100000,
            'gender' => 'Unisex',
            'is_active' => true,
            'availability_status' => 'available', 'availability_source' => 'qammaris_app',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 100000,
            'sku' => 'CHECKOUT-'.uniqid(),
            'stock' => 10,
            'is_active' => true,
        ]);

        return [$product->load('brand'), $variant];
    }

    private function staleSessionItem(ProductVariant $variant): array
    {
        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_name' => 'Stale Product',
            'slug' => 'stale-product',
            'brand_name' => 'Stale Brand',
            'volume' => 1,
            'price' => 1,
            'quantity' => 2,
            'image' => 'https://example.test/stale.png',
        ];
    }

    private function customerData(): array
    {
        return [
            'customer_name' => 'Test Recipient', 'customer_phone' => '081234567890',
            'customer_address' => 'Alamat sintetis untuk pengujian checkout', 'customer_postcode' => '12345',
            'customer_note' => 'Catatan sintetis.', 'checkout_quote' => session('checkout_quote'),
        ];
    }
}
