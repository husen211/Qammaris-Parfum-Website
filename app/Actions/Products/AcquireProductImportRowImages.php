<?php

namespace App\Actions\Products;

use App\Exceptions\InvalidImportedProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImportRow;
use App\Services\ImportedProductImageDownloader;
use App\Services\ProductMediaStorage;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class AcquireProductImportRowImages
{
    public function __construct(
        private ImportedProductImageDownloader $downloader,
        private ProductMediaStorage $mediaStorage,
        private AttachProductImage $attachProductImage
    ) {}

    public function handle(int $rowId): void
    {
        $row = DB::transaction(function () use ($rowId): ?ProductImportRow {
            $lockedRow = ProductImportRow::query()->lockForUpdate()->find($rowId);

            if (! $lockedRow || ! in_array($lockedRow->image_acquisition_status, [
                ProductImportRow::IMAGE_QUEUED,
                ProductImportRow::IMAGE_PROCESSING,
            ], true)) {
                return null;
            }

            $lockedRow->forceFill([
                'image_acquisition_status' => ProductImportRow::IMAGE_PROCESSING,
                'image_acquisition_completed_at' => null,
            ])->save();

            return $lockedRow;
        });

        if (! $row) {
            return;
        }

        foreach ($row->image_acquisition_outcomes ?? [] as $index => $outcome) {
            if (($outcome['status'] ?? null) !== 'pending') {
                continue;
            }

            $product = Product::query()->find($row->applied_product_id);

            if (! $product || $product->publication_status !== Product::PUBLICATION_DRAFT) {
                $this->recordOutcome($rowId, $index, 'blocked', 'Produk tidak lagi berupa draft; gambar tidak diubah.');

                continue;
            }

            if ($product->images()->count() >= ProductImage::MAX_PER_PRODUCT) {
                $this->recordOutcome($rowId, $index, 'blocked', 'Slot gambar produk sudah penuh (maksimum tiga).');

                continue;
            }

            $this->recordOutcome($rowId, $index, 'downloading', null);
            $download = null;
            $storedPath = null;

            try {
                $download = $this->downloader->download((string) $outcome['source_url']);
                $uploadedFile = new UploadedFile(
                    $download['path'],
                    'import-'.$download['checksum'].'.'.$download['extension'],
                    $download['mime_type'],
                    UPLOAD_ERR_OK,
                    true
                );
                $storedPath = $this->mediaStorage->store($uploadedFile);

                DB::transaction(function () use ($rowId, $index, $storedPath, $download): void {
                    $lockedRow = ProductImportRow::query()->lockForUpdate()->findOrFail($rowId);
                    $product = Product::query()->lockForUpdate()->findOrFail($lockedRow->applied_product_id);

                    if ($product->publication_status !== Product::PUBLICATION_DRAFT) {
                        throw new DomainException('Produk tidak lagi berupa draft; gambar tidak diubah.');
                    }

                    $image = $this->attachProductImage->handle($product, $storedPath);
                    $outcomes = $lockedRow->image_acquisition_outcomes ?? [];
                    $current = $outcomes[$index] ?? [];
                    $outcomes[$index] = array_merge($current, [
                        'status' => 'stored',
                        'message' => 'Gambar tersimpan di storage Qammaris.',
                        'product_image_id' => $image->getKey(),
                        'object_key' => $storedPath,
                        'mime_type' => $download['mime_type'],
                        'size' => $download['size'],
                        'width' => $download['width'],
                        'height' => $download['height'],
                        'checksum' => $download['checksum'],
                        'stored_at' => now()->toJSON(),
                    ]);
                    $lockedRow->forceFill(['image_acquisition_outcomes' => $outcomes])->save();
                });
            } catch (InvalidImportedProductImage $exception) {
                $this->recordOutcome($rowId, $index, 'failed', $exception->getMessage());
            } catch (DomainException $exception) {
                $this->cleanupStoredPath($storedPath);
                $this->recordOutcome($rowId, $index, 'blocked', $exception->getMessage());
            } catch (Throwable $exception) {
                $this->cleanupStoredPath($storedPath);
                report($exception);
                $this->recordOutcome($rowId, $index, 'failed', 'Gambar gagal disimpan. Coba ulang kandidat ini.');
            } finally {
                if (is_array($download) && isset($download['path'])) {
                    File::delete($download['path']);
                }
            }
        }

        $this->completeRow($rowId);
    }

    public function markUnexpectedFailure(int $rowId): void
    {
        DB::transaction(function () use ($rowId): void {
            $row = ProductImportRow::query()->lockForUpdate()->find($rowId);

            if (! $row) {
                return;
            }

            $outcomes = collect($row->image_acquisition_outcomes ?? [])->map(function (array $outcome): array {
                if (in_array($outcome['status'] ?? null, ['pending', 'downloading'], true)) {
                    $outcome['status'] = 'failed';
                    $outcome['message'] = 'Worker berhenti sebelum kandidat selesai. Coba ulang.';
                }

                return $outcome;
            })->all();

            $row->forceFill([
                'image_acquisition_status' => ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS,
                'image_acquisition_completed_at' => now(),
                'image_acquisition_outcomes' => $outcomes,
            ])->save();
        });
    }

    private function recordOutcome(int $rowId, int $index, string $status, ?string $message): void
    {
        DB::transaction(function () use ($rowId, $index, $status, $message): void {
            $row = ProductImportRow::query()->lockForUpdate()->findOrFail($rowId);
            $outcomes = $row->image_acquisition_outcomes ?? [];
            $outcomes[$index] = array_merge($outcomes[$index] ?? [], [
                'status' => $status,
                'message' => $message,
            ]);
            $row->forceFill(['image_acquisition_outcomes' => $outcomes])->save();
        });
    }

    private function completeRow(int $rowId): void
    {
        DB::transaction(function () use ($rowId): void {
            $row = ProductImportRow::query()->lockForUpdate()->findOrFail($rowId);
            $outcomes = collect($row->image_acquisition_outcomes ?? []);
            $hasErrors = $outcomes->contains(fn (array $outcome): bool => in_array(
                $outcome['status'] ?? null,
                ['failed', 'blocked', 'pending', 'downloading'],
                true
            ));

            $row->forceFill([
                'image_acquisition_status' => $hasErrors
                    ? ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS
                    : ProductImportRow::IMAGE_COMPLETED,
                'image_acquisition_completed_at' => now(),
            ])->save();
        });
    }

    private function cleanupStoredPath(?string $storedPath): void
    {
        if ($storedPath === null) {
            return;
        }

        if (! $this->mediaStorage->delete([$storedPath])) {
            report(new RuntimeException('Cleanup file import gagal untuk object key baru.'));
        }
    }
}
