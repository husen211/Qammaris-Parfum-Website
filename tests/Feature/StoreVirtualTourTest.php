<?php

namespace Tests\Feature;

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

    public function test_tour_page_renders_viewer_data_fallback_and_breadcrumb(): void
    {
        $response = $this->get('/store/virtual-tour')->assertOk()
            ->assertSee('Virtual Tour Toko Qammaris')
            ->assertSee('data-tour-stage', false)
            ->assertSee('id="virtual-tour-data"', false)
            ->assertSee('data-tour-fallback', false)
            ->assertSee('<link rel="canonical" href="'.route('store.virtual-tour').'"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        foreach (config('store_virtual_tour.spots') as $id => $spot) {
            $response->assertSee('data-tour-goto="'.$id.'"', false)
                ->assertSee(asset("images/virtual-tour/{$id}-preview.webp"), false);
        }

        $html = $response->getContent();
        preg_match('#<script type="application/json" id="virtual-tour-data">(.*?)</script>#s', $html, $match);
        $tour = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(config('store_virtual_tour.start'), $tour['start']);
        $this->assertCount(4, $tour['spots']);
        $this->assertSame(asset('images/virtual-tour/tengah-4096.jpg'), collect($tour['spots'])->firstWhere('id', 'tengah')['images']['small']);
    }

    public function test_every_spot_has_its_images_and_walk_links_go_both_ways(): void
    {
        $spots = config('store_virtual_tour.spots');
        $this->assertArrayHasKey(config('store_virtual_tour.start'), $spots);

        foreach ($spots as $id => $spot) {
            foreach (["{$id}-4096.jpg", "{$id}-6144.jpg", "{$id}-preview.webp"] as $file) {
                $this->assertFileExists(public_path("images/virtual-tour/{$file}"));
            }
            [$width, $height] = getimagesize(public_path("images/virtual-tour/{$id}-4096.jpg"));
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

    public function test_location_page_footer_and_sitemap_link_the_tour(): void
    {
        $this->get('/store/location')->assertOk()
            ->assertSee('href="'.route('store.virtual-tour').'"', false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.route('store.virtual-tour').'</loc>', false);
    }
}
