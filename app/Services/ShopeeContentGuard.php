<?php

namespace App\Services;

use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use DomainException;

class ShopeeContentGuard
{
    public function __construct(private ProductImportPayloadHasher $hasher) {}

    public function assertBatch(ProductImportBatch $batch, User $actor): void
    {
        if ($batch->contract_version !== ShopeeContentPreviewer::VERSION || $batch->actor_id !== $actor->id) {
            throw new DomainException('Impor ini bukan milik akun Anda.');
        }
    }

    public function assertPayload(ProductImportRow $row): void
    {
        if ($row->candidate_action !== 'shopee_content' || $row->provider !== 'shopee' || $row->issues !== []
            || $row->external_product_id !== ($row->normalized_data['source']['id'] ?? null)
            || $row->matched_product_id !== ($row->normalized_data['product_id'] ?? null)
            || ! hash_equals($row->payload_hash, $this->hasher->hash($row->normalized_data, [], 'shopee_content'))) {
            throw new DomainException('Usulan impor berubah atau tidak valid. Upload ulang file sumber.');
        }
    }
}
