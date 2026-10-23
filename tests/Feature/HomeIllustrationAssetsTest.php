<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeIllustrationAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_illustrations_resolve_to_valid_release_assets(): void
    {
        $this->withoutVite();
        $response = $this->get('/')->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $images = (new DOMXPath($document))->query('//img[starts-with(@alt, "Ilustrasi")]');

        $this->assertCount(4, $images);
        foreach ($images as $image) {
            $path = parse_url($image->getAttribute('src'), PHP_URL_PATH);
            $this->assertStringStartsWith('/images/illustrations/', $path);
            $file = public_path(ltrim($path, '/'));
            $this->assertFileExists($file);
            $dimensions = getimagesize($file);
            $this->assertSame(IMAGETYPE_WEBP, $dimensions[2]);
            $this->assertSame((string) $dimensions[0], $image->getAttribute('width'));
            $this->assertSame((string) $dimensions[1], $image->getAttribute('height'));
        }
    }

    /** PERF-01: no hero video downloads before the script picks the one the current layout shows. */
    public function test_hero_videos_wait_for_the_script_and_show_a_poster_first(): void
    {
        $this->withoutVite();
        $document = new DOMDocument;
        @$document->loadHTML($this->get('/')->assertOk()->getContent());
        $videos = (new DOMXPath($document))->query('//video[@data-hero-video]');

        $this->assertCount(4, $videos);
        foreach ($videos as $video) {
            $this->assertFalse($video->hasAttribute('autoplay'));
            $this->assertFalse($video->hasAttribute('src'));
            $this->assertSame(0, $video->getElementsByTagName('source')->length);
            $this->assertSame('none', $video->getAttribute('preload'));
            foreach (['poster', 'data-src'] as $attribute) {
                $this->assertFileExists(public_path(ltrim(parse_url($video->getAttribute($attribute), PHP_URL_PATH), '/')));
            }
        }
    }
}
