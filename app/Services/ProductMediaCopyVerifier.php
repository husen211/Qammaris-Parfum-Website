<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductMediaCopyVerifier
{
    public function __construct(private ProductMediaStorage $productMediaStorage) {}

    public function run(string $sourceDisk, string $targetDisk, bool $apply): array
    {
        if ($sourceDisk === $targetDisk) {
            throw new RuntimeException('Source dan target disk harus berbeda.');
        }

        $records = ProductImage::query()
            ->orderBy('id')
            ->get(['id', 'product_id', 'image_path'])
            ->groupBy('image_path')
            ->map(fn ($images, $path) => $this->processPath(
                source: Storage::disk($sourceDisk),
                target: Storage::disk($targetDisk),
                storedPath: $path,
                imageIds: $images->pluck('id')->all(),
                productIds: $images->pluck('product_id')->unique()->values()->all(),
                apply: $apply,
            ))
            ->values()
            ->all();

        $summary = collect($records)->countBy('status')->sortKeys()->all();
        $failedStatuses = [
            'copy_failed',
            'invalid_path',
            'missing_source',
            'target_check_failed',
            'target_mismatch',
            'verification_failed',
        ];

        return [
            'batch_id' => (string) Str::ulid(),
            'generated_at' => now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry_run',
            'source_disk' => $sourceDisk,
            'target_disk' => $targetDisk,
            'success' => collect($records)->whereIn('status', $failedStatuses)->isEmpty(),
            'summary' => $summary,
            'records' => $records,
        ];
    }

    private function processPath(
        FilesystemAdapter $source,
        FilesystemAdapter $target,
        mixed $storedPath,
        array $imageIds,
        array $productIds,
        bool $apply,
    ): array {
        $record = [
            'path' => is_string($storedPath) ? $storedPath : null,
            'image_ids' => $imageIds,
            'product_ids' => $productIds,
            'status' => null,
            'source' => null,
            'target' => null,
        ];

        $path = $this->productMediaStorage->normalizeProductPath($storedPath);

        if ($path === null || $path !== $storedPath) {
            return $this->withStatus($record, 'invalid_path');
        }

        try {
            if (! $source->exists($path)) {
                return $this->withStatus($record, 'missing_source');
            }

            $record['source'] = $this->fingerprint($source, $path);
        } catch (Throwable) {
            return $this->withStatus($record, 'missing_source');
        }

        try {
            if ($target->exists($path)) {
                $record['target'] = $this->fingerprint($target, $path);

                return $this->withStatus(
                    $record,
                    $this->fingerprintsMatch($record['source'], $record['target'])
                        ? 'already_verified'
                        : 'target_mismatch'
                );
            }
        } catch (Throwable) {
            return $this->withStatus($record, 'target_check_failed');
        }

        if (! $apply) {
            return $this->withStatus($record, 'planned_copy');
        }

        try {
            $this->copy($source, $target, $path);
            $record['target'] = $this->fingerprint($target, $path);
        } catch (Throwable) {
            return $this->withStatus($record, 'copy_failed');
        }

        return $this->withStatus(
            $record,
            $this->fingerprintsMatch($record['source'], $record['target'])
                ? 'copied_verified'
                : 'verification_failed'
        );
    }

    private function fingerprint(FilesystemAdapter $disk, string $path): array
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Object {$path} tidak dapat dibaca.");
        }

        $hash = hash_init('sha256');
        $size = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException("Object {$path} gagal dibaca.");
                }

                hash_update($hash, $chunk);
                $size += strlen($chunk);
            }
        } finally {
            fclose($stream);
        }

        return [
            'size' => $size,
            'sha256' => hash_final($hash),
        ];
    }

    private function copy(FilesystemAdapter $source, FilesystemAdapter $target, string $path): void
    {
        $stream = $source->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Source object {$path} tidak dapat dibaca.");
        }

        try {
            if (! $target->writeStream($path, $stream)) {
                throw new RuntimeException("Target object {$path} gagal ditulis.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function fingerprintsMatch(array $source, array $target): bool
    {
        return $source['size'] === $target['size']
            && hash_equals($source['sha256'], $target['sha256']);
    }

    private function withStatus(array $record, string $status): array
    {
        $record['status'] = $status;

        return $record;
    }
}
