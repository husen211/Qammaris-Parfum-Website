<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCartIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_an_inactive_product_from_an_existing_cart(): void
    {
        [$product, $variant] = $this->createCatalogItem();
        $product->update(['is_active' => false]);

        $response = $this->withSession([
            'cart' => [$variant->id => $this->staleSessionItem($variant)],
        ])->from(route('cart.index'))->post(route('cart.checkout'), $this->customerData());

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas(
            'error',
            'Satu atau lebih produk di daftar inquiry sudah tidak tersedia atau berubah. Tinjau kembali daftar Anda.'
        );
    }

    public function test_checkout_rejects_an_invalid_quantity_from_the_session(): void
    {
        [, $variant] = $this->createCatalogItem();
        $item = $this->staleSessionItem($variant);
        $item['quantity'] = 'not-an-integer';

        $response = $this->withSession([
            'cart' => [$variant->id => $item],
        ])->from(route('cart.index'))->post(route('cart.checkout'), $this->customerData());

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('error');
    }

    public function test_checkout_message_uses_current_database_values_instead_of_session_snapshot(): void
    {
        [$product, $variant] = $this->createCatalogItem();
        $product->brand->update(['name' => 'Current Brand']);
        $product->update(['name' => 'Current Product']);
        $variant->update([
            'volume' => 75,
            'price' => 150000,
        ]);

        $response = $this->withSession([
            'cart' => [$variant->id => $this->staleSessionItem($variant)],
        ])->post(route('cart.checkout'), $this->customerData());

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $message = $query['text'] ?? '';

        $this->assertStringContainsString('Current Brand - Current Product', $message);
        $this->assertStringContainsString('Ukuran: 75 ml', $message);
        $this->assertStringContainsString('Jumlah yang diminati: 2', $message);
        $this->assertStringContainsString('Harga saat ini: Rp 150.000 / item', $message);
        $this->assertStringContainsString('Estimasi nilai produk: Rp 300.000', $message);
        $this->assertStringContainsString('Status website: Konfirmasi stok', $message);
        $this->assertStringContainsString(route('products.show', $product), $message);
        $this->assertStringContainsString('Catatan: Tolong konfirmasi.', $message);
        $this->assertStringContainsString('belum menjadi transaksi atau reservasi', $message);
        $this->assertStringNotContainsString('Stale Product', $message);
        $this->assertStringNotContainsString('SHIPPING DETAILS', $message);
        $this->assertStringNotContainsString('invoice', strtolower($message));
        $this->assertStringNotContainsString('payment', strtolower($message));
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
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 100000,
            'sku' => 'CHECKOUT-001',
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
            'customer_note' => 'Tolong konfirmasi.',
        ];
    }
}
