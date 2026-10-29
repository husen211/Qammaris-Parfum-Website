<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\User;
use App\Services\ImportedProductName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ORD-12: an App draft created while the product was still OTW has no price, so no offer and no way to order it.
 * Once the App price is valid and the size is written in the name, the draft gets its single offer from them.
 */
class FillAppDraftOffer
{
    public function __construct(
        private SyncSingleOffer $offers,
        private ImportedProductName $names,
        private RecordProductAdminChange $audit,
    ) {}

    /** Size for a draft that can get its offer now, otherwise null. */
    public function size(Product $product, array $source): ?int
    {
        $price = $source['price'];
        if ($source['hidden'] || $product->publication_status !== Product::PUBLICATION_DRAFT
            || ! is_int($price) || $price <= 0 || $price > 99999999 || $product->variants()->exists()) {
            return null;
        }

        return $this->names->size($source['name']) ?? $this->names->size($product->name);
    }

    /** Caller owns the transaction and the product lock. */
    public function fill(Product $product, array $source): bool
    {
        $size = $this->size($product, $source);
        if ($size === null) {
            return false;
        }
        $this->offers->handle($product, ['volume' => $size, 'price' => $source['price'], 'sku' => null]);

        return true;
    }

    /**
     * Drafts that were already waiting before ORD-12; the feed only replays new revisions, so the Owner applies these.
     *
     * @return Collection<int, array{product: Product, size: int, price: int}>
     */
    public function waiting(): Collection
    {
        $products = Product::query()->where('publication_status', Product::PUBLICATION_DRAFT)
            ->where('qammaris_app_hidden', false)->doesntHave('variants')
            ->with(['externalIdentities' => fn ($query) => $query->where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)])
            ->whereHas('externalIdentities', fn ($query) => $query->where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP))
            ->orderBy('name')->get();
        $snapshots = DB::table('qammaris_app_products')
            ->whereIn('id', $products->map(fn ($product) => $product->externalIdentities->first()->external_product_id))
            ->pluck('snapshot', 'id');

        return $products->map(function (Product $product) use ($snapshots) {
            $raw = $snapshots->get($product->externalIdentities->first()->external_product_id);
            $source = $raw ? json_decode($raw, true, flags: JSON_THROW_ON_ERROR) : null;
            $size = $source ? $this->size($product, $source) : null;

            return $size === null ? null : ['product' => $product, 'size' => $size, 'price' => $source['price']];
        })->filter()->values();
    }

    /** Fills the previewed drafts that are still eligible, audited as the Owner; returns how many got an offer. */
    public function applyWaiting(User $actor, array $productIds): int
    {
        $filled = 0;
        foreach (array_unique($productIds) as $productId) {
            $uuid = ProductExternalIdentity::where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)
                ->where('product_id', $productId)->value('external_product_id');
            if ($uuid === null) {
                continue;
            }
            $filled += DB::transaction(function () use ($actor, $productId, $uuid): int {
                // Same lock order as the feed: source snapshot first, then the product.
                $raw = DB::table('qammaris_app_products')->where('id', $uuid)->lockForUpdate()->value('snapshot');
                $product = Product::query()->lockForUpdate()->find($productId);
                if ($raw === null || $product === null) {
                    return 0;
                }
                $before = $this->audit->snapshot($product);
                if (! $this->fill($product, json_decode($raw, true, flags: JSON_THROW_ON_ERROR))) {
                    return 0;
                }
                $this->audit->handle($product, $actor, 'product_updated', $before);

                return 1;
            });
        }

        return $filled;
    }
}
