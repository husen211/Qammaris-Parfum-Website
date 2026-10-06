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
            $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
            $this->assertSame((string) $dimensions[0], $image->getAttribute('width'));
            $this->assertSame((string) $dimensions[1], $image->getAttribute('height'));
        }
    }
}
