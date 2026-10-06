<?php

namespace App\Services;

use App\Exceptions\InvalidProductImportFile;
use App\Imports\Products\ShopeeContentXlsx;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ShopeeContentPreviewer
{
    public const VERSION = 'shopee-content-v1';

    public function __construct(private ShopeeContentXlsx $reader, private ShopeeProductCopy $copy,
        private ImportedProductName $names, private ProductImportPayloadHasher $hasher) {}

    public function products()
    {
        return Product::with(['brand', 'category', 'variants', 'images', 'externalIdentities'])
            ->where('publication_status', '!=', Product::PUBLICATION_ARCHIVED)->where('qammaris_app_hidden', false)
            ->whereHas('externalIdentities', fn ($q) => $q->where('provider', 'qammaris_app'))->orderBy('name')->get();
    }

    public function preview(User $actor, UploadedFile $basic, UploadedFile $media): ProductImportBatch
    {
        $copy = $this->reader->read($basic, 'basic');
        $photos = $this->reader->read($media, 'media');
        if (array_diff_key($copy, $photos) || array_diff_key($photos, $copy)) {
            throw new InvalidProductImportFile('Kedua file harus berisi kode produk yang sama. Ekspor Informasi Dasar dan Media untuk pilihan produk yang sama.');
        }
        $products = $this->products();
        $rows = [];
        foreach ($copy as $id => $source) {
            if ($source['name'] !== $photos[$id]['name']) {
                throw new InvalidProductImportFile('Nama produk antarkedua ekspor berbeda. Ekspor ulang kedua file pada waktu yang sama.');
            }
            $source += ['photos' => $photos[$id]['photos']];
            foreach (array_filter($source['photos']) as $url) {
                if (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                    || ! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), config('product_imports.image_acquisition.allowed_hosts'), true)
                    || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PORT) !== null) {
                    throw new InvalidProductImportFile('URL foto bukan sumber Shopee HTTPS yang diizinkan pada baris '.$source['source_row'].'.');
                }
            }
            $bound = ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', (string) $id)->first();
            $target = $bound ? $products->firstWhere('id', $bound->product_id) : null;
            if (! $bound) {
                $matches = $products->filter(function ($p) use ($source) {
                    $key = $this->names->mediaKey($source['name'], $p->brand?->name ?? '');

                    return $key !== null && $key === $this->names->mediaKey($p->name, $p->brand?->name ?? '')
                        && ($this->names->concentration($source['name']) === null || $this->names->concentration($p->name) === null
                            || $this->names->concentration($source['name']) === $this->names->concentration($p->name))
                        && ! $p->externalIdentities->contains('provider', 'shopee');
                });
                $target = $matches->count() === 1 ? $matches->first() : null;
            }
            $data = $this->plan($source, $target);
            if ($bound && ! $target) {
                $data['issues'][] = 'Produk terhubung sedang disembunyikan/diarsipkan atau belum memiliki identitas aplikasi.';
            }
            $rows[] = $data;
        }
        $targets = array_count_values(array_filter(array_column($rows, 'product_id')));
        foreach ($rows as &$row) {
            if ($row['product_id'] && $targets[$row['product_id']] > 1) {
                $row = $this->plan($row['source'], null);
                $row['issues'][] = 'Lebih dari satu produk Shopee mengarah ke produk website yang sama; pilih satu yang benar.';
            }
        }
        unset($row);
        $fingerprint = hash('sha256', hash_file('sha256', $basic->getRealPath()).'|'.hash_file('sha256', $media->getRealPath()));
        $catalog = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        $key = hash('sha256', self::VERSION.'|'.$actor->id.'|'.$fingerprint.'|'.$catalog);

        return DB::transaction(function () use ($rows, $actor, $basic, $media, $fingerprint, $catalog, $key) {
            $batch = ProductImportBatch::firstOrCreate(['idempotency_key' => $key], [
                'actor_id' => $actor->id, 'source_filename' => mb_substr(basename($basic->getClientOriginalName()).' + '.basename($media->getClientOriginalName()), 0, 255),
                'source_size' => $basic->getSize() + $media->getSize(), 'source_fingerprint' => $fingerprint,
                'catalog_state_fingerprint' => $catalog, 'contract_version' => self::VERSION, 'status' => 'previewed',
                'total_rows' => count($rows), 'valid_rows' => count(array_filter($rows, fn ($r) => $r['product_id'] !== null)),
                'review_rows' => count(array_filter($rows, fn ($r) => $r['product_id'] === null)), 'error_rows' => 0,
            ]);
            if ($batch->wasRecentlyCreated) {
                foreach ($rows as $data) {
                    $batch->rows()->create(['line_number' => $data['source']['source_row'], 'status' => $data['product_id'] ? 'valid' : 'review',
                        'candidate_action' => 'shopee_content', 'provider' => 'shopee', 'external_product_id' => $data['source']['id'],
                        'matched_product_id' => $data['product_id'], 'normalized_data' => $data, 'issues' => [],
                        'payload_hash' => $this->hasher->hash($data, [], 'shopee_content')]);
                }
            }

            return $batch;
        });
    }

    public function fingerprint(Product $product): string
    {
        return hash('sha256', app(ProductCatalogRowFingerprint::class)->hash($product->fresh(['variants']))
            .'|'.json_encode($this->images($product), JSON_THROW_ON_ERROR));
    }

    public function images(Product $product): array
    {
        return $product->images()->orderBy('id')->get(['id', 'image_path', 'is_primary', 'sort_order'])->toArray();
    }

    public function assertTarget(Product $p, string $shopeeId): void
    {
        $uuid = $p->externalIdentities()->where('provider', 'qammaris_app')->value('external_product_id');
        $raw = $uuid ? DB::table('qammaris_app_products')->where('id', $uuid)->value('snapshot') : null;
        $source = $raw ? json_decode($raw, true, flags: JSON_THROW_ON_ERROR) : null;
        $owner = ProductExternalIdentity::where('provider', 'shopee')->where('external_product_id', $shopeeId)->value('product_id');
        $other = $p->externalIdentities()->where('provider', 'shopee')->where('external_product_id', '!=', $shopeeId)->exists();
        if (! $source || $source['hidden'] || ! $source['active'] || $source['merged_into'] !== null
            || $p->qammaris_app_hidden || $p->publication_status === 'archived' || ($owner && (int) $owner !== $p->id) || $other) {
            throw new DomainException('Produk tidak dapat dipilih: sudah digunakan produk Shopee lain atau sedang disembunyikan.');
        }
    }

    public function plan(array $source, ?Product $p, bool $replace = false): array
    {
        $data = ['source' => $source, 'product_id' => $p?->id, 'target_fingerprint' => null, 'fields' => [], 'offer_ml' => null,
            'replace_description' => $replace, 'baseline_images' => [], 'issues' => [], 'foto_utama_url' => '', 'foto_2_url' => '', 'foto_3_url' => ''];
        if (! $p) {
            $data['issues'][] = 'Pilih produk website yang sesuai. Produk baru harus masuk dari Qammaris App terlebih dahulu.';

            return $data;
        }
        try {
            $this->assertTarget($p, $source['id']);
        } catch (DomainException $e) {
            $data['product_id'] = null;
            $data['issues'][] = $e->getMessage();

            return $data;
        }
        $size = $this->names->size($source['name']);
        $offers = $p->variants;
        if ($offers->count() > 1 || ($size !== null && $offers->count() === 1 && (int) $offers->first()->volume !== $size)) {
            $data['product_id'] = null;
            $data['issues'][] = 'Ukuran Shopee berbeda dari produk website. Pilih ukuran yang tepat; ukuran lama tidak diganti.';

            return $data;
        }
        $clean = $this->copy->clean($source['description']);
        if ($clean !== '' && ($replace || trim((string) $p->description) === '') && $p->description !== $clean) {
            $data['fields']['description'] = $clean;
        }
        if (! $p->gender && ($gender = $this->copy->gender($source['name'], $clean))) {
            $data['fields']['gender'] = $gender;
        }
        if (! $p->category_id && ($concentration = $this->names->concentration($source['name']))) {
            $categories = Category::active()->where('name', $concentration)->get();
            if ($categories->count() === 1) {
                $data['fields']['category_id'] = $categories->first()->id;
            }
        }
        if ($offers->isEmpty() && $size !== null) {
            $uuid = $p->externalIdentities->firstWhere('provider', 'qammaris_app')->external_product_id;
            $sourcePrice = json_decode(DB::table('qammaris_app_products')->where('id', $uuid)->value('snapshot'), true)['price'] ?? null;
            if ((is_int($sourcePrice) && $sourcePrice > 0 && $sourcePrice <= 99999999) || (float) $p->base_price > 0) {
                $data['offer_ml'] = $size;
            } else {
                $data['issues'][] = 'Lengkapi harga di Qammaris App sebelum membuat ukuran/harga website.';
            }
        }
        $data['target_fingerprint'] = $this->fingerprint($p);
        $data['baseline_images'] = $this->images($p);
        $known = [];
        foreach (ProductImportRow::where('applied_product_id', $p->id)->get() as $old) {
            foreach ($old->image_acquisition_outcomes ?? [] as $index => $outcome) {
                if (($outcome['status'] ?? '') === 'stored' && collect($data['baseline_images'])->contains('image_path', $outcome['object_key'] ?? null)) {
                    $url = $outcome['source_url'] ?? $old->normalized_data[$outcome['slot'] ?? ''] ?? $old->normalized_data['photos'][$index] ?? null;
                    if ($url) {
                        $known[$url] = true;
                    }
                }
            }
        }
        $remaining = max(0, 3 - count($data['baseline_images']));
        if ($data['baseline_images'] === [] && $source['photos'][0] === '') {
            $remaining = 0;
            $data['issues'][] = 'Foto sampul Shopee belum tersedia. Upload manual di editor produk.';
        }
        foreach ($source['photos'] as $index => $url) {
            if ($url !== '' && ! isset($known[$url]) && $remaining > 0) {
                $data[['foto_utama_url', 'foto_2_url', 'foto_3_url'][$index]] = $url;
                $known[$url] = true;
                $remaining--;
            }
        }
        if ($p->description && ! $replace && $p->description !== $clean && $clean !== '') {
            $data['issues'][] = 'Deskripsi website dipertahankan. Centang penggantian hanya bila ingin memakai deskripsi Shopee.';
        }
        if (! $p->gender && ! isset($data['fields']['gender'])) {
            $data['issues'][] = 'Peruntukan belum jelas; lengkapi di editor sebelum diterbitkan.';
        }

        return $data;
    }
}
