<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\InquiryWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectedAvailabilityPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_catalog_labels_follow_source_status_and_restock_does_not_make_available(): void
    {
        $labels = ['available' => 'Tersedia', 'sold_out' => 'Habis', 'unknown' => 'Tanyakan ketersediaan'];
        foreach ($labels as $status => $label) {
            $this->item($status, 'Connected '.$status);
        }
        [$otw] = $this->item('sold_out', 'Connected restock');
        $otw->forceFill(['availability_restock_eta' => '2020-01-01'])->save();
        $this->get(route('products.index'))->assertOk()
            ->assertSee('Habis · Restok segera')->assertSee('Tanyakan ketersediaan')
            ->assertDontSee('Tersedia saat diperiksa')->assertDontSee('Sold out');
        $filtered = $this->get(route('products.index', ['availability' => 'available']));
        $filtered->assertOk()->assertSee('Connected available')->assertDontSee('Connected restock');
        $this->assertSame('sold_out', $otw->fresh()->effective_availability);
    }

    public function test_connected_detail_hides_checked_time_and_verification_copy_even_with_old_timestamp(): void
    {
        [$product, $offer] = $this->item('available', 'Connected available');
        $response = $this->get(route('products.show', $product));
        $response->assertOk()->assertSee('Tersedia')->assertSee('Tambah ke daftar inquiry')
            ->assertDontSee('Pemeriksaan terakhir')->assertDontSee('Tersedia saat diperiksa')
            ->assertDontSee('Konfirmasi kembali sebelum')->assertDontSee('Ketersediaan tetap perlu dikonfirmasi');
        $message = $this->message(app(InquiryWhatsApp::class)->productUrl('6281234567890', $product, $offer, 'stock'));
        $this->assertStringContainsString('Status website: Tersedia', $message);
        $this->assertStringNotContainsString('Mohon konfirmasi', $message);
        $this->assertStringContainsString('belum menjadi transaksi atau reservasi', $message);
    }

    public function test_connected_sold_out_detail_only_offers_restock_and_unknown_remains_neutral(): void
    {
        [$product] = $this->item('sold_out', 'Connected OTW');
        $product->forceFill(['availability_restock_eta' => '2026-10-12'])->save();
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Habis · Restok segera')->assertSee('Tanya restock via WhatsApp')
            ->assertDontSee('Tambah ke daftar inquiry')->assertDontSee('Pemeriksaan terakhir');
        $product->forceFill(['availability_status' => 'unknown', 'availability_restock_eta' => null])->save();
        $this->get(route('products.show', $product))->assertOk()->assertSee('Tanyakan ketersediaan')
            ->assertDontSee('Website tidak menampilkan stok secara real-time')->assertDontSee('Pemeriksaan terakhir');
    }

    public function test_inquiry_rereads_source_labels_and_otw_instead_of_session_snapshot(): void
    {
        [$product, $offer] = $this->item('available', 'Connected inquiry');
        $session = ['cart' => [$offer->id => ['variant_id' => $offer->id, 'quantity' => 2,
            'availability_label' => 'FORGED STALE LABEL', 'requires_stock_confirmation' => true]]];
        $this->withSession($session)->getJson(route('cart.data'))->assertOk()
            ->assertJsonPath('items.0.availability_label', 'Tersedia')
            ->assertJsonPath('items.0.requires_stock_confirmation', false);
        $this->withSession($session)->get(route('cart.index'))->assertOk()->assertSee('Tersedia')
            ->assertDontSee('Admin akan mengonfirmasi stok')->assertDontSee('Stok dan harga terbaru tetap dikonfirmasi')
            ->assertDontSee('FORGED STALE LABEL');
        $product->forceFill(['availability_status' => 'sold_out', 'availability_restock_eta' => '2026-10-12'])->save();
        $this->withSession($session)->getJson(route('cart.data'))->assertOk()
            ->assertJsonPath('items.0.availability_label', 'Habis · Restok segera');
        $response = $this->withSession($session)->post(route('cart.checkout'));
        $message = $this->message($response->headers->get('Location'));
        $this->assertStringContainsString('Status website: Habis · Restok segera', $message);
        $this->assertStringNotContainsString('Mohon konfirmasi stok', $message);
        $this->assertStringNotContainsString('FORGED STALE LABEL', $message);
        $this->assertStringContainsString('belum menjadi transaksi atau reservasi', $message);
    }

    public function test_mixed_inquiry_keeps_legacy_confirmation_without_downgrading_connected_label(): void
    {
        [, $appOffer] = $this->item('available', 'Connected');
        [$manual, $manualOffer] = $this->item('available', 'Manual');
        $manual->update(['availability_source' => 'manual', 'availability_checked_at' => now()]);
        $session = ['cart' => [
            $appOffer->id => ['variant_id' => $appOffer->id, 'quantity' => 1],
            $manualOffer->id => ['variant_id' => $manualOffer->id, 'quantity' => 1],
        ]];
        $this->withSession($session)->getJson(route('cart.data'))->assertOk()
            ->assertJsonPath('items.0.availability_label', 'Tersedia')
            ->assertJsonPath('items.1.availability_label', 'Tersedia saat diperiksa')
            ->assertJsonPath('notice', 'Admin akan mengonfirmasi stok dan harga terbaru. Mengirim inquiry tidak menyimpan stok atau membuat transaksi.');
        $this->withSession($session)->get(route('cart.index'))->assertOk()->assertSee('Admin akan mengonfirmasi stok');
    }

    private function item(string $status, string $name): array
    {
        $brand = Brand::firstOrCreate(['name' => 'Presentation Brand'], ['is_active' => true]);
        $category = Category::firstOrCreate(['name' => 'EDP'], ['is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id, 'category_id' => $category->id, 'name' => $name,
            'description' => 'Presentation test.', 'base_price' => 200000, 'gender' => 'Unisex',
            'is_active' => true, 'publication_status' => 'published', 'availability_status' => $status,
            'availability_source' => 'qammaris_app', 'availability_checked_at' => '2020-01-01 00:00:00',
        ]);
        $offer = ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 200000, 'stock' => 0, 'is_active' => true]);

        return [$product->load('brand'), $offer];
    }

    private function message(?string $url): string
    {
        $this->assertNotNull($url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['text'] ?? '';
    }
}
