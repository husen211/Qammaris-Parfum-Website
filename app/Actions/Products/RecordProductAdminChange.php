<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class RecordProductAdminChange
{
    private const ACTIONS = [
        'product_created', 'product_updated', 'product_archived', 'product_published',
        'image_primary_changed', 'image_moved', 'image_archived',
    ];

    /** Read current DB state, never cached relations or a request body. Caller holds the product lock. */
    public function snapshot(Product $product): array
    {
        $current = $product->fresh();

        return $current->only([
            'name', 'slug', 'brand_id', 'category_id', 'gender', 'is_best_seller',
            'base_price', 'compare_at_price', 'publication_status', 'is_active',
            'availability_status', 'availability_source',
        ]) + [
            'availability_checked_at' => $current->getRawOriginal('availability_checked_at'),
            'description_hash' => $this->hash($current->description),
            'fragrance_notes_hash' => $this->hash($current->fragrance_notes),
            'offers' => $current->variants()->orderBy('id')->get()->map(fn ($offer) => [
                'id' => $offer->id, 'volume' => $offer->volume, 'price' => $offer->price,
                'stock' => $offer->stock, 'is_active' => $offer->is_active,
                'sku_hash' => $this->hash($offer->sku),
            ])->all(),
            'images' => $current->images()->reorder()->orderBy('id')->get()->map(fn ($image) => [
                'id' => $image->id, 'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order, 'key_hash' => $this->hash($image->image_path),
            ])->all(),
        ];
    }

    public function handle(Product $product, User $actor, string $action, array $before, ?int $imageId = null): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Product history must commit with the product mutation.');
        }
        if (! in_array($action, self::ACTIONS, true) || ! $actor->exists) {
            throw new InvalidArgumentException('A known product action and persisted actor are required.');
        }

        $after = $this->snapshot($product);
        $fields = array_keys(array_filter($after, fn ($value, $field) => ! array_key_exists($field, $before) || $before[$field] !== $value, ARRAY_FILTER_USE_BOTH));
        if ($fields === []) {
            return;
        }

        // Only the snapshot allowlist reaches storage; description/notes, URLs and request extras are excluded.
        $old = array_intersect_key($before, array_flip($fields));
        $new = array_intersect_key($after, array_flip($fields));
        DB::table('product_admin_changes')->insert([
            'product_id' => $product->getKey(), 'actor_id' => $actor->getKey(), 'action' => $action,
            'image_id' => $imageId,
            'changed_fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'before' => json_encode($old, JSON_THROW_ON_ERROR),
            'after' => json_encode($new, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function hash(mixed $value): ?string
    {
        return $value === null ? null : hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
