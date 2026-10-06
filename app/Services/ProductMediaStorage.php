<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductMediaStorage
{
    public function store(UploadedFile $file): string
    {
        $path = $file->store($this->directory(), $this->diskName());
        $storedPath = is_string($path) ? $this->normalizeLocalPath($path) : null;
        $path = $this->normalizeProductPath($path);

        if ($path === null || ! $this->exists($path)) {
            if ($storedPath !== null) {
                $this->disk()->delete($storedPath);
            }

            throw new RuntimeException('Uploaded product image could not be verified on storage.');
        }

        return $path;
    }

    public function url(?string $storedPath): ?string
    {
        if ($this->isRemoteUrl($storedPath)) {
            return null;
        }

        $path = $this->normalizeLocalPath($storedPath);

        if ($path === null) {
            return null;
        }

        try {
            if (! $this->disk()->exists($path)) {
                return null;
            }

            return $this->disk()->url($path);
        } catch (Throwable $error) {
            report($error);

            return null;
        }
    }

    public function delete(array $storedPaths): bool
    {
        $paths = array_values(array_filter(
            array_map(
                fn (mixed $path) => is_string($path) ? $this->normalizeLocalPath($path) : null,
                $storedPaths
            ),
            fn (?string $path) => $path !== null
        ));

        if ($paths === []) {
            return true;
        }

        return $this->disk()->delete($paths);
    }

    public function normalizeLocalPath(mixed $storedPath): ?string
    {
        if (! is_string($storedPath)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $storedPath));

        if ($path === '' || $this->isRemoteUrl($path) || str_contains($path, "\0")) {
            return null;
        }

        $path = ltrim($path, '/');

        do {
            $original = $path;

            foreach (['storage/', 'public/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $path = substr($path, strlen($prefix));
                }
            }
        } while ($path !== $original);

        $path = preg_replace('#/+#', '/', $path);

        if (! is_string($path) || $path === '') {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..' || $segment === '') {
                return null;
            }
        }

        return $path;
    }

    public function normalizeProductPath(mixed $storedPath): ?string
    {
        $path = $this->normalizeLocalPath($storedPath);
        $prefix = $this->directory().'/';

        return $path !== null && str_starts_with($path, $prefix) ? $path : null;
    }

    public function exists(string $storedPath): bool
    {
        $path = $this->normalizeProductPath($storedPath);

        if ($path === null) {
            return false;
        }

        try {
            return $this->disk()->exists($path);
        } catch (Throwable $error) {
            report($error);

            return false;
        }
    }

    public function diskName(): string
    {
        $disk = config('media.product_disk', 'public');

        if (! is_string($disk) || trim($disk) === '') {
            throw new RuntimeException('Product media disk is not configured.');
        }

        return trim($disk);
    }

    private function directory(): string
    {
        $directory = config('media.product_directory', 'products');
        $directory = is_string($directory) ? trim($directory, " /\\\t\n\r\0\x0B") : '';

        if ($directory === '' || $this->normalizeLocalPath($directory) !== $directory) {
            throw new RuntimeException('Product media directory is not configured safely.');
        }

        return $directory;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    private function isRemoteUrl(mixed $value): bool
    {
        if (! is_string($value) || ! filter_var(trim($value), FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url(trim($value), PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
