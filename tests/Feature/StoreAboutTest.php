<?php

namespace Tests\Feature;

use App\Models\StoreInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreAboutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_about_uses_owner_story_real_media_and_click_to_load_video(): void
    {
        $response = $this->get('/store/about')->assertOk()
            ->assertSee('Husein')->assertSee('Mengenal parfum saat merantau')
            ->assertSee('Semua produk di toko tersedia testernya.')
            ->assertSee('Sabtu–Kamis · 09.00–21.00 WITA')->assertSee('Jumat tutup')
            ->assertDontSee('paper-test-')->assertDontSee('skin-test-')
            ->assertSee('data-about-hero10', false)->assertDontSee('data-cinematic-hero', false)->assertSee('data-about-spy', false)
            ->assertDontSee('Loading 3D...')->assertDontSee('about-lanyard')
            ->assertDontSee('<iframe', false)
            ->assertSee('https://www.instagram.com/reel/Dd0iMrDJURL/embed/', false)
            ->assertSee('rating dapat berubah')
            ->assertSee('Owen Bryant Owen Bryant')->assertSee('Derry Qlay')
            ->assertSee('Sniff dulu, cocok baru bayar.')
            ->assertSee('5 dari 5 bintang');

        $response->assertViewHas('aboutContent', fn ($content) => count($content['gallery']) === 4
            && count($content['timeline']) === 4 && count($content['reviews']) === 6);
        foreach (['storefront-hero', 'visitors', 'facade', 'construction', 'design-board', 'shelf-plan', 'floor-plan'] as $key) {
            $this->assertSame(1, substr_count($response->getContent(), 'src="'.asset('images/store/'.$key.'-')), 'Photo composition must appear only once: '.$key);
        }
        foreach ([...config('store_about.gallery'), ['key' => 'storefront-hero']] as $photo) {
            foreach ([480, 768, 1200] as $width) {
                $path = public_path('images/store/'.$photo['key'].'-'.$width.'.webp');
                $this->assertFileExists($path);
                $dimensions = getimagesize($path);
                $this->assertLessThanOrEqual($width, $dimensions[0]);
                $this->assertSame('image/webp', $dimensions['mime']);
            }
        }
    }

    public function test_structured_data_matches_visible_hours_without_fabricated_reviews(): void
    {
        $this->get('/store/about')->assertOk()->assertViewHas('aboutSchema', function ($schema) {
            $store = $schema['@graph'][1];
            $hours = $store['openingHoursSpecification'][0];

            return $schema['@graph'][0]['@type'] === 'AboutPage'
                && $store['@type'] === 'Store'
                && $hours['opens'] === '09:00' && $hours['closes'] === '21:00'
                && count($hours['dayOfWeek']) === 6 && ! in_array('Friday', $hours['dayOfWeek'], true)
                && ! isset($store['aggregateRating']) && ! isset($store['review'])
                && $schema['@graph'][2]['@type'] === 'BreadcrumbList';
        });
        $this->get('/store/about?utm_source=test')->assertSee('rel="canonical" href="'.route('store.about').'"', false);
    }

    public function test_stored_address_is_escaped_in_markup_and_json_ld(): void
    {
        StoreInfo::create(['whatsapp_number' => '6285144924931', 'address' => '</script><script>alert("address")</script>']);
        $response = $this->get('/store/about')->assertOk();
        $response->assertDontSee('</script><script>alert("address")</script>', false)
            ->assertSee('&lt;/script&gt;', false)
            ->assertSee('\\u003C/script\\u003E', false);
    }
}
