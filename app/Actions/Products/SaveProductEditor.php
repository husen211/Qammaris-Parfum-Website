<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductMediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SaveProductEditor
{
    public function __construct(
        private SyncSingleOffer $offers,
        private AttachProductImage $images,
        private PublishProduct $publication,
        private ProductMediaStorage $storage,
        private RecordProductAdminChange $audit,
    ) {}

    /**
     * Callers validate editor input before invoking this operation.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $uploads
     */
    public function handle(array $data, array $uploads, bool $publish, User $actor, ?Product $product = null): Product
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $uploads, $publish, $actor, $product, &$storedPaths): Product {
                $creating = $product === null;
                if (! $creating) {
                    $product = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                }
                $before = $creating ? [] : $this->audit->snapshot($product);
                $attributes = $this->attributes($data);

                if ($creating) {
                    $product = Product::create($attributes + [
                        'base_price' => null,
                        'is_active' => false,
                        'publication_status' => Product::PUBLICATION_DRAFT,
                        'published_at' => null,
                        'availability_status' => Product::AVAILABILITY_UNKNOWN,
                    ]);
                } else {
                    $product->update($attributes);
                    $this->updateManualAvailability($product, $data);
                }

                $offer = $this->completeOffer($data['variants'] ?? []);
                if ($offer !== null) {
                    $this->offers->handle($product, $offer);
                }

                foreach ($uploads as $index => $upload) {
                    $path = $this->storage->store($upload);
                    $storedPaths[] = $path;
                    $this->images->handle($product, $path, $creating && $index === 0);
                }

                if ($publish) {
                    $this->publication->handle($product);
                }

                $this->audit->handle($product, $actor, $creating ? 'product_created' : 'product_updated', $before);

                return $product;
            });
        } catch (Throwable $error) {
            // The DB has rolled back; compensate only files created by this save.
            $this->cleanupStoredImages($storedPaths);
            throw $error;
        }
    }

    private function attributes(array $data): array
    {
        $notes = [];
        foreach (['top', 'middle', 'base'] as $group) {
            $notes[$group] = array_values(array_filter(
                array_map('trim', explode(',', (string) ($data["{$group}_notes"] ?? ''))),
                fn (string $note): bool => $note !== ''
            ));
        }

        return [
            'brand_id' => $data['brand_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'fragrance_notes' => $notes,
            'gender' => $data['gender'] ?? null,
            'is_best_seller' => $data['is_best_seller'],
        ];
    }

    private function updateManualAvailability(Product $product, array $data): void
    {
        if (! array_key_exists('availability_status', $data)
            || Product::query()->whereKey($product->id)->lockForUpdate()->value('availability_source') === 'qammaris_app') {
            return;
        }

        if ($data['availability_status'] !== $product->availability_status
            || ($data['availability_confirmed'] ?? false)) {
            $product->update([
                'availability_status' => $data['availability_status'],
                'availability_source' => 'manual',
                'availability_checked_at' => now(),
            ]);
        }
    }

    private function completeOffer(array $variants): ?array
    {
        $offer = array_values($variants)[0] ?? null;

        if (! is_array($offer) || ! isset($offer['volume'], $offer['price'])
            || $offer['volume'] === '' || $offer['price'] === '') {
            return null;
        }

        return $offer;
    }

    private function cleanupStoredImages(array $storedPaths): void
    {
        if ($storedPaths === []) {
            return;
        }

        try {
            if (! $this->storage->delete($storedPaths)) {
                report(new RuntimeException('One or more rolled-back product images could not be deleted.'));
            }
        } catch (Throwable $cleanupError) {
            report($cleanupError);
        }
    }
}
