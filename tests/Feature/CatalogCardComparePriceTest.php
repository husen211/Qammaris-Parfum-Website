<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CAT-PRICE-01: the catalog card shows the struck-through price under the same rule as the detail page. */
class CatalogCardComparePriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_card_strikes_a_higher_compare_price_only(): void
    {
        $this->withoutVite();
        $this->product('Diskon Sintetis', 250000);
        $this->product('Coret Sama Sintetis', 150000);
        $this->product('Tanpa Coret Sintetis', null);

        $html = $this->get(route('products.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'catalog-card__compare'));
        $this->assertStringContainsString('<s class="catalog-card__compare"><span class="sr-only">Harga sebelumnya </span>Rp 250.000</s>', $html);
        $this->assertSame(3, substr_count($html, '<span>Rp 150.000</span>'));
    }

    private function product(string $name, ?int $compareAt): void
    {
        $product = Product::create([
            'name' => $name,
            'description' => 'Test description',
            'base_price' => 150000,
            'compare_at_price' => $compareAt,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => now(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'availability_source' => 'qammaris_app',
        ]);
        ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 150000, 'stock' => 0, 'is_active' => true]);
    }
}
