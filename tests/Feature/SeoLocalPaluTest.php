<?php

namespace Tests\Feature;

use App\Models\StoreInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO-02: the store's Palu landing page, local headings and one NAP across the site, without a distributor claim. */
class SeoLocalPaluTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_homepage_says_what_and_where_and_links_the_store(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<title>Toko Parfum Timur Tengah &amp; Lokal di Palu - Qammaris Perfumes</title>', false)
            ->assertSeeInOrder(['Redefining Middle East Scents', '<h1', 'Toko Parfum Timur Tengah dan Lokal Terlengkap di Kota Palu'], false)
            ->assertSee('Lihat Koleksi Parfum')
            ->assertSee('href="'.route('store.location').'"', false)
            ->assertSee('Kunjungi toko di Palu');
    }

    public function test_no_page_claims_to_be_a_distributor(): void
    {
        foreach (['/', '/products', route('store.location'), route('store.about')] as $url) {
            $this->assertStringNotContainsStringIgnoringCase('distributor', $this->get($url)->assertOk()->getContent(), $url);
        }
    }

    public function test_location_page_is_the_local_landing_page(): void
    {
        $html = $this->get(route('store.location'))->assertOk()
            ->assertSee('<title>Toko Parfum Timur Tengah di Palu - Qammaris Perfumes</title>', false)
            ->assertSee('Jl. Sis Aljufri, Siranindi, Palu Barat')
            ->assertSee('kirim ke seluruh Indonesia')
            ->assertSee('Apakah bisa kirim ke luar Palu?')
            ->assertSee('@qammaris<', false)
            ->assertDontSee('@qammarisperfumes')
            ->assertSee('aria-current="page">Lokasi Toko', false)
            ->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Toko Parfum Timur Tengah di Palu\s*</h1>#', $html);
        preg_match('#<script type="application/ld\+json">(\{"@context":"https://schema.org","@graph".*?)</script>#s', $html, $match);
        $graph = json_decode($match[1], true)['@graph'];
        $this->assertSame(['Store', 'BreadcrumbList'], array_column($graph, '@type'));
    }

    public function test_an_empty_email_leaves_no_empty_contact_row(): void
    {
        $this->assertStringNotContainsString('>Email</p>', $this->get(route('store.location'))->getContent());

        StoreInfo::create(['whatsapp_number' => '6285144924931', 'address' => 'Palu', 'email' => 'halo@qammaris.test']);
        cache()->forget('store_info');
        $this->get(route('store.location'))->assertSee('halo@qammaris.test');
    }

    public function test_footer_shows_the_same_address_on_every_public_page(): void
    {
        foreach (['/', '/products', route('blog.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('Jl. Sis Aljufri, Siranindi, Kec. Palu Barat, Kota Palu, Sulawesi Tengah 94111');
        }
    }

    public function test_catalog_intro_only_on_the_plain_catalog(): void
    {
        $this->get('/products')->assertOk()->assertSee('catalog-header__intro', false)
            ->assertSee('<title>Katalog Parfum Timur Tengah &amp; Lokal - Qammaris Perfumes Palu</title>', false);
        $this->get('/products?sort=price_low')->assertOk()->assertDontSee('catalog-header__intro', false);
        $this->get('/products?page=2')->assertOk()->assertDontSee('catalog-header__intro', false);
    }
}
