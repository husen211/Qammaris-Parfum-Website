<?php

namespace App\Actions\Products;

use App\Models\OnlineOrderItem;
use App\Models\Product;
use App\Models\ProductExternalIdentity;

/**
 * CAT-APP-MERGE-01: the App merges an OTW product into the Majoo product on import. A Website draft follows the merge
 * instead of staying behind as a hidden duplicate; the App's explicit `merged_into` is the only rebind allowed.
 * Published products keep their mapping (hidden tombstone rule, ADR-025).
 */
class FollowQammarisAppMerge
{
    /** Caller owns the transaction and the product lock; returns the audited outcome or null when not merged. */
    public function handle(Product $product, array $snapshot): ?string
    {
        $target = $snapshot['merged_into'];
        if ($target === null || $target === $snapshot['id'] || $product->publication_status !== Product::PUBLICATION_DRAFT) {
            return null;
        }
        $taken = ProductExternalIdentity::where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)
            ->where('external_product_id', $target)->lockForUpdate()->first();
        if ($taken === null) {
            $product->externalIdentities()->where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)
                ->where('external_product_id', $snapshot['id'])->update(['external_product_id' => $target]);

            return 'followed_merge';
        }
        // The surviving App product already has its own Website product: retire this one only if nothing was built on it.
        if ($product->published_at === null && ! $product->images()->exists() && ! OnlineOrderItem::where('product_id', $product->id)->exists()) {
            $product->markArchived();

            return 'archived_merged_duplicate';
        }

        return 'merged_review';
    }
}
