<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ProductImportPayloadHasher;
use App\Services\ProductImportProductSnapshot;
use App\Services\ShopeeContentPreviewer;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApplyShopeeContent
{
    public function __construct(private ShopeeContentPreviewer $preview, private ProductImportPayloadHasher $hasher,
        private ProductImportProductSnapshot $snapshots) {}

    public function assertBatch(ProductImportBatch $batch, User $actor): void
    {
        if ($batch->contract_version !== ShopeeContentPreviewer::VERSION || $batch->actor_id !== $actor->id) {
            throw new DomainException('Impor ini bukan milik akun Anda.');
        }
    }

    public function choose(ProductImportBatch $batch, ProductImportRow $row, User $actor, int $productId, bool $replace, ?int $confirmedWebsiteMl = null): bool
    {
        $this->assertBatch($batch, $actor);

        return DB::transaction(function () use ($batch, $row, $actor, $productId, $replace, $confirmedWebsiteMl) {
            ProductImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $locked = $batch->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $this->assertPayload($locked);
            if (! in_array($locked->apply_status, ['pending', 'blocked_protected', 'skipped_no_changes'], true)) {
                throw new DomainException('Baris sudah diterapkan; upload ulang file untuk perubahan berikutnya.');
            }
            if ($batch->rows()->where('id', '!=', $row->id)->where('matched_product_id', $productId)->exists()) {
                throw new DomainException('Produk website ini sudah dipilih pada baris lain.');
            }
            $product = $this->preview->products()->firstWhere('id', $productId);
            if (! $product) {
                throw new DomainException('Pilih produk aplikasi yang masih aktif di katalog admin.');
            }
            $data = $this->preview->plan($locked->normalized_data['source'], $product, $replace, $confirmedWebsiteMl);
            if (! $data['product_id'] && $data['size_mismatch'] === null) {
                throw new DomainException(implode(' ', $data['issues']));
            }
            $this->savePlan($locked, $data, $actor, 'Admin memilih produk dan melihat perubahan sebelum penerapan.');
            $batch->forceFill(['valid_rows' => $batch->rows()->whereNotNull('matched_product_id')->count(),
                'review_rows' => $batch->rows()->whereNull('matched_product_id')->count()])->save();

            return $data['product_id'] !== null;
        });
    }

    public function refresh(ProductImportBatch $batch, User $actor): int
    {
        $this->assertBatch($batch, $actor);

        return DB::transaction(function () use ($batch, $actor) {
            ProductImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $products = $this->preview->products()->keyBy('id');
            $rows = $batch->rows()->whereIn('apply_status', ['pending', 'blocked_protected'])->lockForUpdate()->get();
            foreach ($rows as $row) {
                $this->assertPayload($row);
                $old = $row->normalized_data;
                $id = $row->matched_product_id ?? $old['selected_product_id'] ?? null;
                $product = $products->get($id);
                // A size confirmation belongs to this precise source/target pair, not future sizes.
                $confirmed = $old['confirmed_website_ml'] ?? null;
                if ($confirmed !== null && ($old['size_mismatch']['website_ml'] ?? null) !== $confirmed) {
                    $confirmed = null;
                }
                $data = $this->preview->plan($old['source'], $product, $old['replace_description'], $confirmed);
                if ($data['product_id'] && $batch->rows()->where('id', '!=', $row->id)->where('matched_product_id', $data['product_id'])->exists()) {
                    $data = $this->preview->plan($old['source'], null);
                    $data['issues'][] = 'Produk website sudah dipilih pada baris lain.';
                }
                $this->savePlan($row, $data, $actor, 'Admin memeriksa ulang perubahan. Sumber Shopee asli dan produk website tidak diubah.');
            }
            $batch->forceFill(['valid_rows' => $batch->rows()->whereNotNull('matched_product_id')->count(),
                'review_rows' => $batch->rows()->whereNull('matched_product_id')->count()])->save();

            return $rows->count();
        });
    }

    private function savePlan(ProductImportRow $row, array $data, User $actor, string $message): void
    {
        $row->forceFill(['matched_product_id' => $data['product_id'], 'normalized_data' => $data,
            'status' => $data['product_id'] ? 'valid' : 'review', 'apply_status' => 'pending', 'apply_message' => null,
            'payload_hash' => $this->hasher->hash($data, [], 'shopee_content'), 'resolved_by' => $actor->id, 'resolved_at' => now(),
            'resolution_status' => $data['product_id'] ? 'resolved' : 'review',
            'resolution_fields' => ['product_id' => $data['selected_product_id'], 'replace_description' => $data['replace_description'],
                'confirmed_website_ml' => $data['confirmed_website_ml'], 'size_mismatch' => $data['size_mismatch']],
            'resolution_before_snapshot' => $row->normalized_data, 'resolution_after_snapshot' => $data,
            'resolution_message' => $message])->save();
    }

    public function handle(ProductImportBatch $batch, User $actor): int
    {
        $this->assertBatch($batch, $actor);

        return DB::transaction(function () use ($batch, $actor) {
            // Use the feed's checkpoint -> source -> product ordering when an offer is completed.
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            $batch = ProductImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->assertBatch($batch, $actor);
            $rows = $batch->rows()->where('apply_status', 'pending')->whereNotNull('matched_product_id')->lockForUpdate()->get();
            $productIds = $rows->pluck('matched_product_id');
            $uuids = DB::table('product_external_identities')->whereIn('product_id', $productIds)->where('provider', 'qammaris_app')->pluck('external_product_id');
            DB::table('qammaris_app_products')->whereIn('id', $uuids)->orderBy('id')->lockForUpdate()->get();
            Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
            $count = 0;
            // Payload corruption is not a recoverable product conflict: roll back the operation.
            foreach ($rows as $r) {
                $this->assertPayload($r);
            }
            foreach ($rows as $r) {
                $data = $r->normalized_data;
                $p = Product::with(['variants', 'externalIdentities'])->find($r->matched_product_id);
                try {
                    if (! $p) {
                        throw new DomainException('Produk website tidak ditemukan. Pilih produk lain.');
                    }
                    $this->preview->assertTarget($p, $r->external_product_id);
                    if (($data['fingerprint_version'] ?? null) !== ShopeeContentPreviewer::FINGERPRINT_VERSION
                        || ! hash_equals($data['target_fingerprint'], $this->preview->fingerprint($p))) {
                        throw new DomainException('Perubahan baris ini perlu diperiksa ulang. Tekan Periksa ulang perubahan, lalu lihat hasil sebelum menerapkan lagi. Produk lain tetap diproses.');
                    }
                } catch (DomainException $error) {
                    $r->forceFill(['apply_status' => 'blocked_protected', 'apply_message' => $error->getMessage()])->save();

                    continue;
                }
                if (! $this->preview->hasChanges($data, $p)) {
                    $r->forceFill(['apply_status' => 'skipped_no_changes', 'applied_product_id' => $p->id, 'applied_at' => now(),
                        'apply_message' => 'Tidak ada perubahan konten; produk dan foto dipertahankan.'])->save();

                    continue;
                }
                $before = $this->snapshots->capture($p);
                app(MapExternalProductIdentity::class)->handle($p, 'shopee', $r->external_product_id);
                if ($data['fields']) {
                    $p->forceFill($data['fields'])->save();
                }
                if ($data['offer_ml']) {
                    app(SyncSingleOffer::class)->handle($p, ['volume' => $data['offer_ml'], 'price' => $p->base_price, 'stock' => 0]);
                }
                $r->forceFill(['apply_status' => 'updated', 'applied_product_id' => $p->id, 'before_snapshot' => $before,
                    'after_snapshot' => $this->snapshots->capture($p), 'applied_at' => now(),
                    'apply_message' => 'Konten dilengkapi; harga/status aplikasi dan publikasi dipertahankan.'])->save();
                $count++;
            }
            $batch->forceFill(['status' => 'applied', 'applied_by' => $actor->id, 'applied_at' => now(),
                'applied_rows' => $batch->rows()->where('apply_status', 'updated')->count(),
                'blocked_rows' => $batch->rows()->where(fn ($q) => $q->whereNull('matched_product_id')->orWhere('apply_status', 'blocked_protected'))->count()])->save();

            return $count;
        });
    }

    public function publish(ProductImportBatch $batch, User $actor, array $rowIds): int
    {
        $this->assertBatch($batch, $actor);

        return DB::transaction(function () use ($batch, $actor, $rowIds) {
            DB::table('qammaris_app_sync_states')->where('id', 'products')->lockForUpdate()->first();
            ProductImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $rows = $batch->rows()->whereIn('id', $rowIds)->lockForUpdate()->get();
            if ($rows->count() !== count($rowIds)) {
                throw new DomainException('Pilihan produk tidak berasal dari impor ini.');
            }
            $ids = $rows->pluck('applied_product_id')->filter();
            $uuids = DB::table('product_external_identities')->whereIn('product_id', $ids)->where('provider', 'qammaris_app')->pluck('external_product_id');
            DB::table('qammaris_app_products')->whereIn('id', $uuids)->orderBy('id')->lockForUpdate()->get();
            Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $count = 0;
            foreach ($rows as $r) {
                $this->assertPayload($r);
                if ($r->apply_status !== 'updated' || ! $r->applied_product_id) {
                    throw new DomainException('Terapkan konten produk terlebih dahulu.');
                }
                $p = Product::whereKey($r->applied_product_id)->lockForUpdate()->firstOrFail();
                $this->preview->assertTarget($p, $r->external_product_id);
                if ($p->publication_status === 'published') {
                    continue;
                }
                if (in_array($r->image_acquisition_status, ['queued', 'processing'], true)) {
                    throw new DomainException('Tunggu pengunduhan foto selesai sebelum menerbitkan.');
                }
                $before = $this->snapshots->capture($p);
                app(PublishProduct::class)->handle($p);
                $r->forceFill(['resolution_status' => 'published', 'resolved_by' => $actor->id, 'resolved_at' => now(),
                    'resolution_before_snapshot' => $before, 'resolution_after_snapshot' => $this->snapshots->capture($p),
                    'resolution_message' => 'Admin menerbitkan pilihan yang lolos syarat publikasi.'])->save();
                $count++;
            }

            return $count;
        });
    }

    public function assertPayload(ProductImportRow $r): void
    {
        if ($r->candidate_action !== 'shopee_content' || $r->provider !== 'shopee' || $r->issues !== []
            || $r->external_product_id !== ($r->normalized_data['source']['id'] ?? null)
            || $r->matched_product_id !== ($r->normalized_data['product_id'] ?? null)
            || ! hash_equals($r->payload_hash, $this->hasher->hash($r->normalized_data, [], 'shopee_content'))) {
            throw new DomainException('Usulan impor berubah atau tidak valid. Upload ulang file sumber.');
        }
    }
}
