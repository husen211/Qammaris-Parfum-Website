<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeNewArrivalsTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(config('media.product_disk', 'public'));
        $this->now = Carbon::parse('2026-11-17 10:00:00', 'Asia/Makassar');
        $this->travelTo($this->now);
    }

    public function test_newest_five_first_publications_within_sixty_days_with_photo_price_and_not_sold_out(): void
    {
        $brand = Brand::create(['name' => 'Arrival Brand', 'is_active' => true]);
        $expected = [];
        foreach (range(1, 6) as $daysAgo) {
            $expected[] = $this->product('Baru '.$daysAgo, $daysAgo, ['brand_id' => $brand->id])->id;
        }
        $old = $this->product('Lama', 61);
        $this->product('Tanpa Foto', 1, photo: false);
        $this->product('Foto Hilang', 1, fileExists: false);
        $this->product('Tanpa Harga', 1, price: 0);
        $this->product('Tanpa Penawaran', 1, offer: false);
        $this->product('Habis', 1, ['availability_status' => Product::AVAILABILITY_SOLD_OUT]);
        $this->product('Draf', 1, ['publication_status' => Product::PUBLICATION_DRAFT]);
        $this->product('Arsip', 1, ['publication_status' => Product::PUBLICATION_ARCHIVED]);
        $this->product('Nonaktif', 1, ['is_active' => false]);
        $this->product('Disembunyikan App', 1)->forceFill(['qammaris_app_hidden' => true])->save();
        $this->product('Belum Terbit', null);
        // Same publication moment: the newer id comes first.
        $tieOlder = $this->product('Seri A', 0);
        $tieNewer = $this->product('Seri B', 0);

        $response = $this->get(route('home'))->assertOk();
        $arrivals = $response->viewData('newArrivals');

        $this->assertSame([$tieNewer->id, $tieOlder->id, ...array_slice($expected, 0, 3)], $arrivals->pluck('id')->all());
        foreach ($arrivals as $product) {
            $this->assertTrue($product->relationLoaded('brand'));
            $this->assertTrue($product->relationLoaded('primaryImage'));
            $this->assertTrue($product->relationLoaded('activeOffer'));
        }
        $this->assertNotContains($old->id, $arrivals->pluck('id'));

        $section = $this->section($response->getContent());
        $this->assertSame(5, substr_count($section, 'data-new-arrival-item'));
        $this->assertSame(5, substr_count($section, 'data-new-arrival-layer'));
        $this->assertStringContainsString('Baru Tiba', $section);
        $this->assertStringContainsString('Arrival Brand', $section);
        $this->assertStringContainsString('100 ml', $section);
        foreach (['Tanpa Foto', 'Foto Hilang', 'Tanpa Harga', 'Habis', 'Draf', 'Arsip', 'Nonaktif', 'Disembunyikan App', 'Belum Terbit', 'Lama'] as $excluded) {
            $this->assertStringNotContainsString('data-name="'.$excluded.'"', $section);
        }
    }

    public function test_a_product_sixty_days_old_still_qualifies_and_one_second_older_does_not(): void
    {
        $edge = $this->product('Tepat 60 Hari', 60);
        $tooOld = $this->product('Lewat 60 Hari', null, ['published_at' => $this->now->copy()->subDays(60)->subSecond()->utc()]);
        $this->product('Baru 1', 1);
        $this->product('Baru 2', 2);

        $ids = $this->get(route('home'))->viewData('newArrivals')->pluck('id');
        $this->assertContains($edge->id, $ids);
        $this->assertNotContains($tooOld->id, $ids);
    }

    public function test_fewer_than_three_qualifying_products_hide_the_section(): void
    {
        $this->product('Baru 1', 1);
        $this->product('Baru 2', 2);
        $this->product('Habis', 1, ['availability_status' => Product::AVAILABILITY_SOLD_OUT]);
        $this->product('Tanpa Foto', 1, photo: false);

        $response = $this->get(route('home'))->assertOk();
        $this->assertCount(0, $response->viewData('newArrivals'));
        $response->assertDontSee('data-new-arrival', false)->assertDontSee('Baru Tiba')->assertSee('id="best-seller"', false);
    }

    public function test_section_sits_before_best_sellers_without_any_price_and_links_to_newest_catalog(): void
    {
        $this->product('Arrival Satu', null, ['published_at' => Carbon::parse('2026-11-12 08:00:00', 'Asia/Makassar')->utc(), 'compare_at_price' => 250000]);
        $this->product('Arrival Dua', 2);
        $this->product('Arrival Tiga', 3);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'id="best-seller"'), strpos($html, 'id="new-arrival"'));
        $this->assertGreaterThan(strpos($html, 'data-brand-carousel'), strpos($html, 'id="new-arrival"'));

        $section = $this->section($html);
        $this->assertStringNotContainsString('Rp', $section);
        $this->assertStringNotContainsString('150.000', $section);
        $this->assertStringNotContainsString('250.000', $section);
        $this->assertStringContainsString('Baru · 12 Nov', $section);
        $this->assertStringContainsString('New Arrival', $section);
        $this->assertStringContainsString('Lihat semua produk baru', $section);
        $this->assertStringContainsString('href="'.e(route('products.index', ['sort' => 'latest'])).'"', $section);
        $this->assertStringContainsString('aria-labelledby="new-arrival-heading"', $section);
        $this->assertSame(1, substr_count($section, 'data-new-arrival-stage'));
        $this->assertStringContainsString('aria-label="Jeda pergantian produk"', $section);
    }

    private function section(string $html): string
    {
        $start = strpos($html, '<section id="new-arrival"');
        $this->assertNotFalse($start, 'Baru Tiba section is rendered');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    private function product(string $name, ?int $daysAgo, array $overrides = [], bool $photo = true, bool $fileExists = true, int $price = 150000, bool $offer = true): Product
    {
        $product = Product::create(array_merge([
            'name' => $name,
            'description' => 'Test description',
            'base_price' => 150000,
            'is_best_seller' => true,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'published_at' => $daysAgo === null ? null : $this->now->copy()->subDays($daysAgo)->utc(),
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'availability_source' => 'qammaris_app',
        ], $overrides));
        if ($offer) {
            ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => $price, 'stock' => 0, 'is_active' => true]);
        }
        if ($photo) {
            $path = 'products/arrival-'.$product->id.'.webp';
            if ($fileExists) {
                Storage::disk(config('media.product_disk', 'public'))->put($path, 'image');
            }
            ProductImage::create(['product_id' => $product->id, 'image_path' => $path, 'is_primary' => true, 'sort_order' => 0]);
        }

        return $product;
    }
}
