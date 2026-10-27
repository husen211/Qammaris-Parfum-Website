<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreVirtualTourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_location_page_has_the_tour_cover_dialog_and_data(): void
    {
        $response = $this->get('/store/location')->assertOk()
            ->assertSee('id="virtual-tour"', false)
            ->assertSee('Masuk ke toko Qammaris')
            ->assertSee('data-tour-enter', false)
            ->assertSee('<dialog class="vt-dialog"', false)
            ->assertSee('data-tour-zoom="0.5" aria-pressed="true"', false)
            ->assertSee('data-tour-fallback', false)
            ->assertSee(asset('images/virtual-tour/cover.webp'), false);

        $tour = $this->tourData($response->getContent());
        $this->assertSame(config('store_virtual_tour.start'), $tour['start']);
        $this->assertSame(['pintu-masuk', 'tengah', 'depan-kasir'], array_column($tour['spots'], 'id'));
        $this->assertSame(asset('images/virtual-tour/tengah-4096.webp'), $tour['spots'][1]['images']['small']);
        foreach ($tour['spots'] as $spot) {
            $response->assertSee('data-tour-goto="'.$spot['id'].'"', false);
        }
    }

    public function test_homepage_shows_the_tour_right_above_the_journal(): void
    {
        BlogPost::create([
            'title' => 'Artikel Journal', 'excerpt' => 'Ringkasan.', 'content' => '<p>Isi.</p>', 'featured_image' => null,
            'author' => 'Qammaris Team', 'category' => 'Tips', 'is_published' => true, 'published_at' => now()->subMinute(),
            'view_count' => 0, 'meta_description' => 'Meta.',
        ]);

        $html = $this->get('/')->assertOk()->assertSee('Masuk ke toko Qammaris')->getContent();

        $tour = strpos($html, 'id="virtual-tour"');
        $this->assertNotFalse($tour);
        $this->assertGreaterThan(strpos($html, 'id="how-to-order"'), $tour);
        $this->assertLessThan(strpos($html, 'Qammaris Journal</h2>'), $tour);
        $this->assertSame(1, substr_count($html, 'data-virtual-tour'));
    }

    public function test_tour_has_no_page_of_its_own_and_links_point_to_the_location_page(): void
    {
        $this->get('/store/virtual-tour')->assertNotFound();
        $this->get('/store/location')->assertSee('href="'.route('store.location').'#virtual-tour"', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('virtual-tour');
    }

    public function test_every_spot_has_its_images_and_walk_links_go_both_ways(): void
    {
        $spots = config('store_virtual_tour.spots');
        $this->assertArrayHasKey(config('store_virtual_tour.start'), $spots);
        $this->assertFileExists(public_path('images/virtual-tour/cover.webp'));
        $this->assertFileExists(public_path('images/virtual-tour/intro.webp'));
        $this->assertFileDoesNotExist(public_path('images/virtual-tour/dalam-tengah-4096.webp'));
        // The Hostinger CDN shrinks JPEG/PNG to 1600 px (800 px on phones) but passes WebP through unchanged.
        $this->assertSame([], glob(public_path('images/virtual-tour/*.{jpg,jpeg,png}'), GLOB_BRACE));

        foreach ($spots as $id => $spot) {
            foreach (["{$id}-4096.webp", "{$id}-6144.webp", "{$id}-preview.webp"] as $file) {
                $this->assertFileExists(public_path("images/virtual-tour/{$file}"));
            }
            [$width, $height] = getimagesize(public_path("images/virtual-tour/{$id}-4096.webp"));
            $this->assertSame([4096, 2048], [$width, $height], "{$id} must stay a 2:1 panorama within 4096 px");

            $this->assertNotEmpty($spot['links'], "{$id} has no way out");
            foreach ($spot['links'] as $link) {
                $this->assertArrayHasKey($link['to'], $spots);
                $this->assertNotSame($id, $link['to']);
                $this->assertContains($id, array_column($spots[$link['to']]['links'], 'to'), "{$link['to']} has no way back to {$id}");
                $this->assertTrue($link['pitch'] < 0 && $link['pitch'] > -45, 'arrows sit on the floor in front of the viewer');
            }
        }
    }

    private function tourData(string $html): array
    {
        preg_match('#<script type="application/json" data-tour-data>(.*?)</script>#s', $html, $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
