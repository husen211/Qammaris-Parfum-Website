<?php

namespace App\Services;

use App\Exceptions\InvalidImportedProductImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class ImportedProductImageDownloader
{
    /**
     * @return array{path: string, mime_type: string, extension: string, size: int, width: int, height: int, checksum: string}
     */
    public function download(string $sourceUrl): array
    {
        $this->validateSourceUrl($sourceUrl);

        $directory = storage_path('app/private/product-import-images');
        File::ensureDirectoryExists($directory);
        $temporaryPath = tempnam($directory, 'qammaris-import-');

        if ($temporaryPath === false) {
            throw new InvalidImportedProductImage('File sementara gambar tidak dapat dibuat.');
        }

        $maxBytes = max(1, (int) config('product_imports.image_acquisition.max_bytes', 8 * 1024 * 1024));

        try {
            $response = Http::accept('image/jpeg,image/png,image/webp')
                ->connectTimeout(max(1, (int) config('product_imports.image_acquisition.connect_timeout_seconds', 5)))
                ->timeout(max(1, (int) config('product_imports.image_acquisition.timeout_seconds', 20)))
                ->withOptions([
                    'allow_redirects' => false,
                    'sink' => $temporaryPath,
                    'on_headers' => function ($response) use ($maxBytes): void {
                        $contentLength = $response->getHeaderLine('Content-Length');

                        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $maxBytes) {
                            throw new InvalidImportedProductImage('Ukuran gambar melebihi batas yang dikonfigurasi.');
                        }
                    },
                    'progress' => function (int $downloadTotal, int $downloadedBytes) use ($maxBytes): void {
                        if ($downloadTotal > $maxBytes || $downloadedBytes > $maxBytes) {
                            throw new InvalidImportedProductImage('Ukuran gambar melebihi batas yang dikonfigurasi.');
                        }
                    },
                ])
                ->get($sourceUrl);
        } catch (InvalidImportedProductImage $exception) {
            File::delete($temporaryPath);

            throw $exception;
        } catch (ConnectionException) {
            File::delete($temporaryPath);

            throw new InvalidImportedProductImage('Sumber gambar tidak dapat dihubungi.');
        } catch (Throwable $exception) {
            File::delete($temporaryPath);
            report($exception);

            throw new InvalidImportedProductImage('Gambar gagal diunduh dari sumber.');
        }

        if ($response->redirect()) {
            File::delete($temporaryPath);

            throw new InvalidImportedProductImage('Redirect URL gambar ditolak. Gunakan URL file final.');
        }

        if (! $response->successful()) {
            File::delete($temporaryPath);

            throw new InvalidImportedProductImage('Sumber gambar merespons HTTP '.$response->status().'.');
        }

        clearstatcache(true, $temporaryPath);

        if (File::size($temporaryPath) === 0 && $response->body() !== '') {
            File::put($temporaryPath, $response->body());
            clearstatcache(true, $temporaryPath);
        }

        try {
            return $this->inspect($temporaryPath, $maxBytes);
        } catch (Throwable $exception) {
            File::delete($temporaryPath);

            if ($exception instanceof InvalidImportedProductImage) {
                throw $exception;
            }

            report($exception);

            throw new InvalidImportedProductImage('Isi file sumber bukan gambar yang didukung.');
        }
    }

    private function validateSourceUrl(string $sourceUrl): void
    {
        $parts = parse_url($sourceUrl);
        $host = is_array($parts) ? mb_strtolower((string) ($parts['host'] ?? '')) : '';
        $allowedHosts = config('product_imports.image_acquisition.allowed_hosts', []);

        if (! is_array($parts)
            || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false
            || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            throw new InvalidImportedProductImage('URL sumber gambar harus HTTPS tanpa credential atau port khusus.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            || ! in_array($host, is_array($allowedHosts) ? $allowedHosts : [], true)) {
            throw new InvalidImportedProductImage('Host URL gambar belum diizinkan untuk import.');
        }
    }

    /**
     * @return array{path: string, mime_type: string, extension: string, size: int, width: int, height: int, checksum: string}
     */
    private function inspect(string $temporaryPath, int $maxBytes): array
    {
        $size = File::size($temporaryPath);

        if ($size < 1 || $size > $maxBytes) {
            throw new InvalidImportedProductImage('Ukuran gambar kosong atau melebihi batas yang dikonfigurasi.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $mimeTypes = config('product_imports.image_acquisition.allowed_mime_types', []);

        if (! is_string($mimeType) || ! is_array($mimeTypes) || ! isset($mimeTypes[$mimeType])) {
            throw new InvalidImportedProductImage('Format gambar harus JPEG, PNG, atau WebP.');
        }

        $dimensions = @getimagesize($temporaryPath);
        $maxDimension = max(1, (int) config('product_imports.image_acquisition.max_dimension', 12000));

        if (! is_array($dimensions)
            || ($dimensions[0] ?? 0) < 1
            || ($dimensions[1] ?? 0) < 1
            || $dimensions[0] > $maxDimension
            || $dimensions[1] > $maxDimension) {
            throw new InvalidImportedProductImage('Dimensi gambar tidak valid atau terlalu besar.');
        }

        return [
            'path' => $temporaryPath,
            'mime_type' => $mimeType,
            'extension' => $mimeTypes[$mimeType],
            'size' => $size,
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'checksum' => hash_file('sha256', $temporaryPath),
        ];
    }
}
