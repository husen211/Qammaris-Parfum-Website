<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMediaR2RehearsalCommandTest extends TestCase
{
    private const SAMPLE_PATH = 'products/existing-sample.jpg';

    private const SAMPLE_CONTENTS = 'existing-sample-image';

    private const SYNTHETIC_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private string $r2Root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->r2Root = storage_path('framework/testing/disks/r2-rehearsal-'.Str::uuid());

        config([
            'app.env' => 'testing',
            'media.product_disk' => 'r2',
            'media.product_directory' => 'products',
            'filesystems.disks.r2' => [
                'driver' => 'local',
                'root' => $this->r2Root,
                'url' => 'https://media.example.test',
                'throw' => true,
            ],
        ]);
        Storage::forgetDisk('r2');
        Storage::disk('r2')->put(self::SAMPLE_PATH, self::SAMPLE_CONTENTS);
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('r2');
        File::deleteDirectory($this->r2Root);

        parent::tearDown();
    }

    public function test_rehearsal_verifies_existing_media_uploads_and_cleans_synthetic_object(): void
    {
        $synthetic = base64_decode(self::SYNTHETIC_PNG, true);

        Http::fake(function (Request $request) use ($synthetic) {
            return str_ends_with($request->url(), '/'.self::SAMPLE_PATH)
                ? Http::response(self::SAMPLE_CONTENTS, 200, ['Content-Type' => 'image/jpeg'])
                : Http::response($synthetic, 200, ['Content-Type' => 'image/png']);
        });

        $this->runCommand(apply: true)
            ->expectsOutputToContain('"cleanup_verified": true')
            ->assertSuccessful();

        $this->assertSame([self::SAMPLE_PATH], Storage::disk('r2')->allFiles('products'));
    }

    public function test_rehearsal_stops_before_upload_when_sample_checksum_does_not_match(): void
    {
        Http::fake();

        $this->artisan('product-media:rehearse-r2', [
            '--sample-path' => self::SAMPLE_PATH,
            '--expected-size' => strlen(self::SAMPLE_CONTENTS),
            '--expected-sha256' => str_repeat('0', 64),
            '--apply' => true,
        ])->assertFailed();

        $this->assertSame([self::SAMPLE_PATH], Storage::disk('r2')->allFiles('products'));
        Http::assertNothingSent();
    }

    public function test_rehearsal_cleans_synthetic_object_when_public_delivery_verification_fails(): void
    {
        Http::fake(function (Request $request) {
            return str_ends_with($request->url(), '/'.self::SAMPLE_PATH)
                ? Http::response(self::SAMPLE_CONTENTS, 200, ['Content-Type' => 'image/jpeg'])
                : Http::response('wrong-body', 200, ['Content-Type' => 'image/png']);
        });

        $this->runCommand(apply: true)->assertFailed();

        $this->assertSame([self::SAMPLE_PATH], Storage::disk('r2')->allFiles('products'));
    }

    public function test_rehearsal_rejects_a_non_r2_active_disk(): void
    {
        config(['media.product_disk' => 'public']);

        $this->runCommand()->assertFailed();
        Http::assertNothingSent();
    }

    private function runCommand(bool $apply = false)
    {
        $arguments = [
            '--sample-path' => self::SAMPLE_PATH,
            '--expected-size' => strlen(self::SAMPLE_CONTENTS),
            '--expected-sha256' => hash('sha256', self::SAMPLE_CONTENTS),
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        }

        return $this->artisan('product-media:rehearse-r2', $arguments);
    }
}
