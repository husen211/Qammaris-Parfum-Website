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

    public function choose(ProductImportBatch $batch, ProductImportRow $row, User $actor, int $productId, bool $replace): void
    {
        $this->assertBatch($batch, $actor);
        DB::transaction(function () use ($batch, $row, $actor, $productId, $replace) {
            ProductImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $locked = $batch->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $this->assertPayload($locked);
            if ($locked->apply_status !== 'pending') {
                throw new DomainException('Baris sudah diterapkan; upload ulang file untuk perubahan berikutnya.');
            }
            if ($batch->rows()->where('id', '!=', $row->id)->where('matched_product_id', $productId)->exists()) {
                throw new DomainException('Produk website ini sudah dipilih pada baris lain.');
            }
            $product = $this->preview->products()->firstWhere('id', $productId);
            if (! $product) {
                throw new DomainException('Pilih produk aplikasi yang masih aktif di katalog admin.');
            }
            $data = $this->preview->plan($locked->normalized_data['source'], $product, $replace);
            if (! $data['product_id']) {
                throw new DomainException(implode(' ', $data['issues']));
            }
            $locked->forceFill(['matched_product_id' => $productId, 'normalized_data' => $data, 'status' => 'valid',
                'payload_hash' => $this->hasher->hash($data, [], 'shopee_content'), 'resolved_by' => $actor->id, 'resolved_at' => now(),
                'resolution_status' => 'resolved', 'resolution_fields' => ['product_id' => $productId, 'replace_description' => $replace],
                'resolution_before_snapshot' => $locked->normalized_data, 'resolution_after_snapshot' => $data,
                'resolution_message' => 'Admin memilih produk dan melihat usulan yang diperbarui sebelum penerapan.'])->save();
            $batch->forceFill(['valid_rows' => $batch->rows()->whereNotNull('matched_product_id')->count(),
                'review_rows' => $batch->rows()->whereNull('matched_product_id')->count()])->save();
        });
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
            foreach ($rows as $r) {
                $this->assertPayload($r);
                $data = $r->normalized_data;
                $p = Product::with('variants')->findOrFail($r->matched_product_id);
                $this->preview->assertTarget($p, $r->external_product_id);
                if (! hash_equals($data['target_fingerprint'], $this->preview->fingerprint($p))) {
                    throw new DomainException('Data produk berubah setelah diperiksa. Pilih kembali produk pada baris tersebut untuk memperbarui usulan, lalu terapkan lagi.');
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
                'blocked_rows' => $batch->rows()->whereNull('matched_product_id')->count()])->save();

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
