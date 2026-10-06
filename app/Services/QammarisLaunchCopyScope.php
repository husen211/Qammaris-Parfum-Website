<?php

namespace App\Services;

use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Models\Product;
use RuntimeException;

class QammarisLaunchCopyScope
{
    public const VERSION = 'launch-copy-v1';

    public function assertEnvironment(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            throw new RuntimeException('Launch copy is restricted to local/testing/staging.');
        }
    }

    public function assertRow(Product $product, array $data): void
    {
        $this->assertEnvironment();
        foreach ($data as $field => $value) {
            if (! in_array($field, ['product_id', 'expected_updated_at', 'expected_row_fingerprint', 'deskripsi_produk', 'gender', '_changes'], true)
                && ! in_array($value, ['', null, []], true)) {
                throw new RuntimeException('Launch copy may propose description and audience only.');
            }
        }
        foreach ($data['_changes'] ?? [] as $change) {
            if (! in_array($change['field'] ?? '', ['deskripsi_produk', 'gender'], true)) {
                throw new RuntimeException('Launch copy contains a protected field.');
            }
        }
        if ($product->publication_status !== Product::PUBLICATION_DRAFT || $product->is_active || $product->qammaris_app_hidden
            || ! $product->externalIdentities()->where('provider', 'qammaris_app')->exists()
            || ! $product->externalIdentities()->where('provider', 'shopee')->exists()) {
            throw new RuntimeException('Launch copy requires a visible connected Shopee draft.');
        }
        $blockers = app(EvaluateProductPublicationReadiness::class)->handle($product);
        if (array_diff(array_keys($blockers), ['description', 'gender']) !== []) {
            throw new RuntimeException('Launch copy requires the photographed structurally complete cohort.');
        }
    }
}
