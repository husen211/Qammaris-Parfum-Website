<?php

namespace App\Console\Commands;

use App\Services\ProductMediaStorage;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RehearseR2ProductMedia extends Command
{
    protected $signature = 'product-media:rehearse-r2
                            {--sample-path= : Existing canonical product object key}
                            {--expected-size= : Expected sample size in bytes}
                            {--expected-sha256= : Expected sample SHA-256}
                            {--apply : Write, publicly fetch, and delete one synthetic PNG object}';

    protected $description = 'Verify the staging R2 read/write delivery path and clean up the synthetic object';

    public function handle(ProductMediaStorage $mediaStorage): int
    {
        $uploadedPath = null;
        $temporaryFile = null;
        $diskName = $mediaStorage->diskName();

        try {
            $this->assertSafeEnvironment($diskName);

            $samplePath = $mediaStorage->normalizeProductPath($this->option('sample-path'));
            $expectedSize = filter_var($this->option('expected-size'), FILTER_VALIDATE_INT);
            $expectedSha256 = strtolower(trim((string) $this->option('expected-sha256')));

            if ($samplePath === null || $expectedSize === false || $expectedSize < 1 || ! preg_match('/^[a-f0-9]{64}$/', $expectedSha256)) {
                throw new RuntimeException('Sample path, size, atau SHA-256 tidak valid.');
            }

            $sample = $this->verifyStoredObject(
                $mediaStorage,
                $samplePath,
                $expectedSize,
                $expectedSha256,
            );

            $result = [
                'environment' => app()->environment(),
                'disk' => $diskName,
                'sample' => $sample,
                'synthetic' => null,
            ];

            if ((bool) $this->option('apply')) {
                $bytes = $this->syntheticPng();
                $temporaryFile = tempnam(sys_get_temp_dir(), 'qammaris-r2-rehearsal-');

                if ($temporaryFile === false || file_put_contents($temporaryFile, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Fixture sintetis tidak dapat disiapkan.');
                }

                $upload = new UploadedFile($temporaryFile, 'p4-06-rehearsal.png', 'image/png', null, true);
                $uploadedPath = $mediaStorage->store($upload);
                $synthetic = $this->verifyStoredObject(
                    $mediaStorage,
                    $uploadedPath,
                    strlen($bytes),
                    hash('sha256', $bytes),
                );
                $result['synthetic'] = $synthetic + ['cleanup_verified' => false];
            }

            if ($uploadedPath !== null) {
                $this->deleteSyntheticObject($mediaStorage, $uploadedPath);
                $result['synthetic']['cleanup_verified'] = true;
                $uploadedPath = null;
            }

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->info('R2 staging media rehearsal selesai; object sintetis sudah dibersihkan.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            report($error);
            $this->error('R2 staging media rehearsal gagal: '.$error->getMessage());

            return self::FAILURE;
        } finally {
            if ($uploadedPath !== null) {
                try {
                    $this->deleteSyntheticObject($mediaStorage, $uploadedPath);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                    $this->error('Cleanup object sintetis gagal; review object key pada log aplikasi diperlukan.');
                }
            }

            if (is_string($temporaryFile) && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function assertSafeEnvironment(string $diskName): void
    {
        if (! app()->environment(['staging', 'testing'])) {
            throw new RuntimeException('Command ini hanya boleh dijalankan pada staging atau automated test.');
        }

        if ($diskName !== 'r2') {
            throw new RuntimeException('PRODUCT_MEDIA_DISK harus bernilai r2 selama rehearsal.');
        }
    }

    private function verifyStoredObject(
        ProductMediaStorage $mediaStorage,
        string $path,
        int $expectedSize,
        string $expectedSha256,
    ): array {
        $disk = Storage::disk($mediaStorage->diskName());

        if (! $mediaStorage->exists($path)) {
            throw new RuntimeException("Object {$path} tidak tersedia pada disk aktif.");
        }

        $contents = $disk->get($path);
        $actualSize = strlen($contents);
        $actualSha256 = hash('sha256', $contents);

        if ($actualSize !== $expectedSize || ! hash_equals($expectedSha256, $actualSha256)) {
            throw new RuntimeException("Checksum atau ukuran object {$path} tidak cocok.");
        }

        $url = $mediaStorage->url($path);

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Public URL object {$path} tidak valid.");
        }

        $response = Http::retry(3, 250, throw: false)->timeout(30)->get($url);
        $contentType = strtolower(trim(strtok((string) $response->header('Content-Type'), ';') ?: ''));
        $publicContents = $response->body();

        if (! $response->successful() || ! str_starts_with($contentType, 'image/')) {
            throw new RuntimeException("Public delivery object {$path} tidak menghasilkan response gambar yang sukses.");
        }

        if (strlen($publicContents) !== $expectedSize || ! hash_equals($expectedSha256, hash('sha256', $publicContents))) {
            throw new RuntimeException("Public delivery object {$path} tidak cocok dengan object storage.");
        }

        return [
            'path' => $path,
            'size' => $actualSize,
            'sha256' => $actualSha256,
            'delivery_status' => $response->status(),
            'delivery_content_type' => $contentType,
            'delivery_host' => parse_url($url, PHP_URL_HOST),
        ];
    }

    private function deleteSyntheticObject(ProductMediaStorage $mediaStorage, string $path): void
    {
        if (! $mediaStorage->delete([$path]) || $mediaStorage->exists($path)) {
            throw new RuntimeException("Object sintetis {$path} tidak dapat dibersihkan.");
        }
    }

    private function syntheticPng(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        if ($bytes === false) {
            throw new RuntimeException('Fixture PNG sintetis tidak valid.');
        }

        return $bytes;
    }
}
