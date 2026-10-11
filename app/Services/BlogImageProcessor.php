<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BlogImageProcessor
{
    public function available(): bool
    {
        return config('media.blog_resize', true) && extension_loaded('gd') && function_exists('imagewebp');
    }

    /** The verified original is never replaced. Only new derivative paths are compensated. */
    public function prepare(UploadedFile $file, string $disk, string $path, array $options = []): array
    {
        if (hash('sha256', Storage::disk($disk)->get($path)) !== hash('sha256', $file->getContent())) {
            throw new RuntimeException('Original image verification failed');
        }

        return $this->prepareBytes($file->getContent(), $disk, $path, $options);
    }

    public function prepareBytes(string $bytes, string $disk, string $path, array $options = []): array
    {
        $size = getimagesizefromstring($bytes);
        if (! $size || strlen($bytes) > 5 * 1024 * 1024 || $size[0] > 6000 || $size[1] > 6000 || $size[0] * $size[1] > 12000000) {
            throw ValidationException::withMessages(['image' => 'Gambar maksimal 12 megapiksel. Kecilkan gambar lalu coba lagi.']);
        }
        [$width, $height] = $size;
        $result = ['disk' => $disk, 'path' => $path, 'width' => $width, 'height' => $height,
            'checksum' => hash('sha256', $bytes), 'variants' => [],
            'crop' => $options['crop'] ?? 'original', 'focal_x' => (int) ($options['focal_x'] ?? 50), 'focal_y' => (int) ($options['focal_y'] ?? 50),
            'processing_warning' => ! $this->available() ? 'GD/WebP belum aktif; gambar asli digunakan tanpa resize/crop.' : ($width < 1600 || $height < 900 ? 'Gambar lebih kecil dari target 1600 × 900; tidak diperbesar.' : null)];
        if (! $this->available()) {
            return $result;
        }
        $limit = trim(ini_get('memory_limit'));
        $unit = strtolower(substr($limit, -1));
        $budget = (int) $limit * match ($unit) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1
        };
        if ($budget > 0 && memory_get_usage(true) + $width * $height * 12 + 32 * 1024 * 1024 > $budget) {
            throw ValidationException::withMessages(['image' => 'Gambar melebihi kapasitas pemrosesan server. Kecilkan ke sekitar 1600 × 900 lalu coba lagi.']);
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            throw ValidationException::withMessages(['image' => 'Gambar tidak dapat diproses. Pilih file JPEG, PNG, atau WebP yang valid.']);
        }
        try {
            // One original set plus the chosen crop; a fresh prefix retains previous generations.
            $prefix = 'blog/variants/'.Str::uuid();
            foreach (array_unique(['original', $result['crop']]) as $crop) {
                [$sx, $sy, $sw, $sh] = $this->rectangle($width, $height, $crop, $result['focal_x'], $result['focal_y']);
                $sizes = array_unique(array_map(fn ($target) => min($target, $sw), [480, 768, 1200, 1600]));
                foreach ($sizes as $targetWidth) {
                    $targetHeight = max(1, (int) round($targetWidth * $sh / $sw));
                    $output = imagecreatetruecolor($targetWidth, $targetHeight);
                    try {
                        imagealphablending($output, false);
                        imagesavealpha($output, true);
                        if (! imagecopyresampled($output, $source, 0, 0, $sx, $sy, $targetWidth, $targetHeight, $sw, $sh)) {
                            throw new RuntimeException('Image resampling failed');
                        }
                        ob_start();
                        try {
                            $ok = imagewebp($output, null, 82);
                            $encoded = ob_get_contents();
                        } finally {
                            ob_end_clean();
                        }
                        if (! $ok || ! $encoded || ! getimagesizefromstring($encoded)) {
                            throw new RuntimeException('Image encoding failed');
                        }
                        $key = $prefix.'/'.str_replace(':', '-', $crop).'-'.$targetWidth.'.webp';
                        // Track before write so partial writes are also removed on failure.
                        $result['variants'][] = ['path' => $key, 'crop' => $crop, 'width' => $targetWidth, 'height' => $targetHeight];
                        $storage = Storage::disk($disk);
                        if (! $storage->put($key, $encoded) || ! $storage->exists($key) || hash('sha256', $storage->get($key)) !== hash('sha256', $encoded)) {
                            throw new RuntimeException('Unverified image derivative');
                        }
                    } finally {
                        imagedestroy($output);
                    }
                }
            }

            return $result;
        } catch (Throwable $error) {
            $this->discard($result);
            throw $error;
        } finally {
            imagedestroy($source);
        }
    }

    public function discard(array $prepared): void
    {
        foreach ($prepared['variants'] ?? [] as $variant) {
            try {
                Storage::disk($prepared['disk'])->delete($variant['path']);
            } catch (Throwable $error) {
                Log::error('blog.derivative_cleanup_failed', ['exception' => $error::class]);
            }
        }
    }

    private function rectangle(int $width, int $height, string $crop, int $x, int $y): array
    {
        if ($crop === 'original') {
            return [0, 0, $width, $height];
        }
        $ratio = match ($crop) {
            '16:9' => 16 / 9, '4:3' => 4 / 3, '1:1' => 1, default => throw new RuntimeException('Invalid crop')
        };
        $sw = max(1, min($width, (int) floor($height * $ratio)));
        $sh = max(1, min($height, (int) floor($width / $ratio)));

        return [max(0, min($width - $sw, (int) round($width * $x / 100 - $sw / 2))),
            max(0, min($height - $sh, (int) round($height * $y / 100 - $sh / 2))), $sw, $sh];
    }
}
