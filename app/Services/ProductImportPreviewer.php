<?php

namespace App\Services;

use App\Exceptions\InvalidProductImportFile;
use App\Imports\Products\CanonicalProductCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ProductImportPreviewer
{
    public function catalogStateFingerprint(): string
    {
        return $this->lookupContext()['catalog_state_fingerprint'];
    }

    /**
     * @return array{
     *     fingerprint: string,
     *     catalog_state_fingerprint: string,
     *     rows: array<int, array<string, mixed>>,
     *     summary: array{total: int, valid: int, review: int, error: int},
     *     skipped_blank_rows: array<int, int>
     * }
     */
    public function preview(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || $contents === '') {
            throw new InvalidProductImportFile('File CSV kosong atau tidak dapat dibaca.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidProductImportFile('File harus menggunakan encoding UTF-8.');
        }

        $fingerprint = hash('sha256', $contents);
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new InvalidProductImportFile('File CSV tidak dapat diproses.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        try {
            $header = fgetcsv($stream, escape: '');

            if ($header === false) {
                throw new InvalidProductImportFile('Header CSV tidak ditemukan.');
            }

            $header[0] = $this->removeBom((string) ($header[0] ?? ''));
            $header = array_map(fn (mixed $value): string => trim((string) $value), $header);
            $this->validateHeader($header);

            $rawRows = [];
            $blankRows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($stream, escape: '')) !== false) {
                $lineNumber++;

                if ($this->isBlankRow($values)) {
                    $blankRows[] = $lineNumber;

                    continue;
                }

                if (count($values) !== count(CanonicalProductCsv::HEADERS)) {
                    $rawRows[] = [
                        'line_number' => $lineNumber,
                        'structural_error' => sprintf(
                            'Jumlah kolom tidak sesuai: ditemukan %d, seharusnya %d.',
                            count($values),
                            count(CanonicalProductCsv::HEADERS)
                        ),
                    ];
                } else {
                    $rawRows[] = [
                        'line_number' => $lineNumber,
                        'data' => array_combine(CanonicalProductCsv::HEADERS, $values),
                    ];
                }

                if (count($rawRows) > CanonicalProductCsv::MAX_ROWS) {
                    throw new InvalidProductImportFile('File hanya boleh berisi maksimum 1.000 baris data.');
                }
            }
        } finally {
            fclose($stream);
        }

        if ($rawRows === []) {
            throw new InvalidProductImportFile('File tidak mempunyai baris produk untuk dipreview.');
        }

        $context = $this->lookupContext();
        $seenIdentities = [];
        $rows = [];

        foreach ($rawRows as $rawRow) {
            $rows[] = $this->previewRow($rawRow, $context, $seenIdentities);
        }

        return [
            'fingerprint' => $fingerprint,
            'catalog_state_fingerprint' => $context['catalog_state_fingerprint'],
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'valid')),
                'review' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'review')),
                'error' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'error')),
            ],
            'skipped_blank_rows' => $blankRows,
        ];
    }

    /**
     * @param  array<int, string>  $header
     */
    private function validateHeader(array $header): void
    {
        if (count($header) !== count(array_unique($header))) {
            throw new InvalidProductImportFile('Header CSV tidak boleh mempunyai nama kolom duplikat.');
        }

        if ($header !== CanonicalProductCsv::HEADERS) {
            throw new InvalidProductImportFile(
                'Header CSV tidak sesuai kontrak Qammaris. Unduh template terbaru dan pertahankan urutan kolom.'
            );
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function isBlankRow(array $values): bool
    {
        return collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    private function removeBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupContext(): array
    {
        $brands = Brand::query()->orderBy('id')->get();
        $categories = Category::query()->orderBy('id')->get();
        $products = Product::query()
            ->orderBy('id')
            ->get(['id', 'name', 'publication_status', 'updated_at']);
        $identities = ProductExternalIdentity::query()
            ->with('product:id,name,publication_status')
            ->orderBy('id')
            ->get();

        $state = [
            'brands' => $brands->map(fn (Brand $brand): array => [
                $brand->id,
                $brand->name,
                $brand->is_active,
                $brand->updated_at?->toJSON(),
            ])->all(),
            'categories' => $categories->map(fn (Category $category): array => [
                $category->id,
                $category->name,
                $category->is_active,
                $category->updated_at?->toJSON(),
            ])->all(),
            'products' => $products->map(fn (Product $product): array => [
                $product->id,
                $product->name,
                $product->publication_status,
                $product->updated_at?->toJSON(),
            ])->all(),
            'identities' => $identities->map(fn (ProductExternalIdentity $identity): array => [
                $identity->id,
                $identity->product_id,
                $identity->provider,
                $identity->external_product_id,
                $identity->updated_at?->toJSON(),
            ])->all(),
        ];

        return [
            'brands' => $brands->keyBy(fn (Brand $brand): string => $this->key($brand->name)),
            'categories' => $categories->keyBy(fn (Category $category): string => $this->key($category->name)),
            'identities' => $identities->keyBy(fn (ProductExternalIdentity $identity): string => $identity->provider.'|'.$identity->external_product_id),
            'product_names' => $products->groupBy(fn (Product $product): string => $this->key($product->name)),
            'catalog_state_fingerprint' => hash(
                'sha256',
                json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $rawRow
     * @param  array<string, mixed>  $context
     * @param  array<string, int>  $seenIdentities
     * @return array<string, mixed>
     */
    private function previewRow(array $rawRow, array $context, array &$seenIdentities): array
    {
        if (isset($rawRow['structural_error'])) {
            return [
                'line_number' => $rawRow['line_number'],
                'status' => 'error',
                'action' => 'conflict',
                'data' => [],
                'matched_product' => null,
                'issues' => [$this->issue('error', 'baris', $rawRow['structural_error'])],
            ];
        }

        $data = collect($rawRow['data'])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->all();
        $data['provider'] = mb_strtolower($data['provider']);
        $data['terlaris'] = $this->normalizeBoolean($data['terlaris']);
        $data['top_notes'] = $this->normalizeNotes($data['top_notes']);
        $data['middle_notes'] = $this->normalizeNotes($data['middle_notes']);
        $data['base_notes'] = $this->normalizeNotes($data['base_notes']);

        $issues = [];
        $this->validateRequired($data, $issues);
        $this->validateProvider($data, $issues);
        $this->validateNumbers($data, $issues);
        $this->validateControlledValues($data, $issues);
        $this->validateTaxonomy($data, $context, $issues);
        $this->validateImages($data, $issues);

        $identityKey = $data['provider'].'|'.$data['kode_produk'];
        $matchedIdentity = $context['identities']->get($identityKey);
        $action = $matchedIdentity ? 'update' : 'create';

        if ($data['provider'] !== '' && $data['kode_produk'] !== '') {
            if (isset($seenIdentities[$identityKey])) {
                $issues[] = $this->issue(
                    'error',
                    'kode_produk',
                    'Provider dan kode produk duplikat dengan baris '.$seenIdentities[$identityKey].'.'
                );
                $action = 'conflict';
            } else {
                $seenIdentities[$identityKey] = $rawRow['line_number'];
            }
        }

        if (! $matchedIdentity && $data['nama_produk'] !== '') {
            $sameNames = $context['product_names']->get($this->key($data['nama_produk']), collect());

            if ($sameNames->isNotEmpty()) {
                $issues[] = $this->issue(
                    'review',
                    'nama_produk',
                    'Nama produk sudah ada, tetapi tidak mempunyai mapping provider+kode ini. Jangan gabungkan otomatis.'
                );
            }
        }

        if ($matchedIdentity?->product?->publication_status === Product::PUBLICATION_PUBLISHED) {
            $issues[] = $this->issue(
                'review',
                'kode_produk',
                'Mapping mengarah ke produk published. Apply akan menahannya untuk review manual.'
            );
        }

        if ($matchedIdentity?->product?->publication_status === Product::PUBLICATION_ARCHIVED) {
            $issues[] = $this->issue(
                'review',
                'kode_produk',
                'Mapping mengarah ke produk archived. Apply akan menahannya untuk review manual.'
            );
        }

        $status = collect($issues)->contains('severity', 'error')
            ? 'error'
            : (collect($issues)->contains('severity', 'review') ? 'review' : 'valid');

        return [
            'line_number' => $rawRow['line_number'],
            'status' => $status,
            'action' => $action,
            'data' => $data,
            'matched_product' => $matchedIdentity?->product ? [
                'id' => $matchedIdentity->product->id,
                'name' => $matchedIdentity->product->name,
                'publication_status' => $matchedIdentity->product->publication_status,
            ] : null,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateRequired(array $data, array &$issues): void
    {
        foreach ([
            'provider' => 'Provider wajib diisi.',
            'kode_produk' => 'Kode produk wajib diisi.',
            'nama_produk' => 'Nama produk wajib diisi.',
        ] as $field => $message) {
            if ($data[$field] === '') {
                $issues[] = $this->issue('error', $field, $message);
            }
        }

        if (mb_strlen($data['kode_produk']) > 191) {
            $issues[] = $this->issue('error', 'kode_produk', 'Kode produk maksimal 191 karakter.');
        }

        if (mb_strlen($data['nama_produk']) > 255) {
            $issues[] = $this->issue('error', 'nama_produk', 'Nama produk maksimal 255 karakter.');
        }

        if (mb_strlen($data['deskripsi_produk']) > 20000) {
            $issues[] = $this->issue('error', 'deskripsi_produk', 'Deskripsi produk maksimal 20.000 karakter.');
        }

        foreach (['brand', 'gender', 'kategori'] as $field) {
            if (mb_strlen($data[$field]) > 255) {
                $issues[] = $this->issue('error', $field, 'Nilai maksimal 255 karakter.');
            }
        }

        foreach (['deskripsi_produk', 'harga', 'brand', 'gender', 'kategori', 'ukuran_ml'] as $field) {
            if ($data[$field] === '') {
                $issues[] = $this->issue('review', $field, 'Field ini masih kosong dan perlu dilengkapi sebelum publish.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateProvider(array $data, array &$issues): void
    {
        if ($data['provider'] !== '' && ! in_array($data['provider'], ProductExternalIdentity::SUPPORTED_PROVIDERS, true)) {
            $issues[] = $this->issue('error', 'provider', 'Provider hanya boleh shopee atau majoo.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateNumbers(array $data, array &$issues): void
    {
        if ($data['harga'] !== '' && (! preg_match('/^\d+(?:\.\d{1,2})?$/', $data['harga'])
            || (float) $data['harga'] <= 0
            || (float) $data['harga'] > 99999999.99)) {
            $issues[] = $this->issue('error', 'harga', 'Harga harus angka positif tanpa pemisah ribuan.');
        }

        if ($data['stok'] !== '' && (! ctype_digit($data['stok']) || (int) $data['stok'] > 999999)) {
            $issues[] = $this->issue('error', 'stok', 'Stok harus bilangan bulat 0–999999.');
        }

        if ($data['ukuran_ml'] !== '' && (! ctype_digit($data['ukuran_ml'])
            || (int) $data['ukuran_ml'] < 1
            || (int) $data['ukuran_ml'] > 10000)) {
            $issues[] = $this->issue('error', 'ukuran_ml', 'Ukuran harus bilangan bulat 1–10000 ml.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateControlledValues(array $data, array &$issues): void
    {
        if ($data['gender'] !== '' && ! in_array($data['gender'], ['Unisex', 'Pria', 'Wanita'], true)) {
            $issues[] = $this->issue('error', 'gender', 'Gender harus Unisex, Pria, atau Wanita.');
        }

        if ($data['terlaris'] === null) {
            $issues[] = $this->issue('error', 'terlaris', 'Terlaris harus ya/tidak, true/false, atau 1/0.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, Collection<string, mixed>>  $context
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateTaxonomy(array $data, array $context, array &$issues): void
    {
        foreach ([
            'brand' => ['collection' => 'brands', 'label' => 'Brand'],
            'kategori' => ['collection' => 'categories', 'label' => 'Kategori'],
        ] as $field => $definition) {
            if ($data[$field] === '') {
                continue;
            }

            $taxonomy = $context[$definition['collection']]->get($this->key($data[$field]));

            if (! $taxonomy) {
                $issues[] = $this->issue('review', $field, $definition['label'].' belum ditemukan di admin.');
            } elseif (! $taxonomy->is_active) {
                $issues[] = $this->issue('review', $field, $definition['label'].' ditemukan tetapi sedang nonaktif.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateImages(array $data, array &$issues): void
    {
        $hasImage = false;

        foreach (['foto_utama_url', 'foto_2_url', 'foto_3_url'] as $field) {
            if ($data[$field] === '') {
                continue;
            }

            $hasImage = true;
            $scheme = parse_url($data[$field], PHP_URL_SCHEME);

            if (mb_strlen($data[$field]) > 2048
                || filter_var($data[$field], FILTER_VALIDATE_URL) === false
                || mb_strtolower((string) $scheme) !== 'https') {
                $issues[] = $this->issue('error', $field, 'URL gambar harus HTTPS valid dan maksimal 2048 karakter.');
            }
        }

        if (! $hasImage) {
            $issues[] = $this->issue('review', 'foto_utama_url', 'Belum ada URL gambar sumber untuk direview.');
        }
    }

    private function normalizeBoolean(string $value): ?bool
    {
        if ($value === '') {
            return false;
        }

        return match (mb_strtolower($value)) {
            '1', 'true', 'ya' => true,
            '0', 'false', 'tidak' => false,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function normalizeNotes(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return collect(explode('|', $value))
            ->map(fn (string $note): string => trim($note))
            ->filter()
            ->unique(fn (string $note): string => $this->key($note))
            ->values()
            ->all();
    }

    /**
     * @return array{severity: string, field: string, message: string}
     */
    private function issue(string $severity, string $field, string $message): array
    {
        return compact('severity', 'field', 'message');
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
