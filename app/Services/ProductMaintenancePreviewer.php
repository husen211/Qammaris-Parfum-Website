<?php

namespace App\Services;

use App\Exceptions\InvalidProductImportFile;
use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Throwable;

class ProductMaintenancePreviewer
{
    public function __construct(private ProductCatalogRowFingerprint $rowFingerprint) {}

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
            throw new InvalidProductImportFile('File CSV maintenance kosong atau tidak dapat dibaca.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidProductImportFile('File harus menggunakan encoding UTF-8.');
        }

        $fingerprint = hash('sha256', $contents);
        [$rawRows, $blankRows] = $this->readRows($contents);
        $context = $this->lookupContext();
        $seenProductIds = [];
        $rows = [];

        foreach ($rawRows as $rawRow) {
            $rows[] = $this->previewRow($rawRow, $context, $seenProductIds);
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

    /** @return array{array<int, array<string, mixed>>, array<int, int>} */
    private function readRows(string $contents): array
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new InvalidProductImportFile('File CSV maintenance tidak dapat diproses.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        try {
            $header = fgetcsv($stream, escape: '');

            if ($header === false) {
                throw new InvalidProductImportFile('Header CSV maintenance tidak ditemukan.');
            }

            $header[0] = $this->removeBom((string) ($header[0] ?? ''));
            $header = array_map(fn (mixed $value): string => trim((string) $value), $header);
            $this->validateHeader($header);

            $rawRows = [];
            $blankRows = [];
            $lineNumber = 1;

            while (($values = fgetcsv($stream, escape: '')) !== false) {
                $lineNumber++;

                if (collect($values)->every(fn (mixed $value): bool => trim((string) $value) === '')) {
                    $blankRows[] = $lineNumber;

                    continue;
                }

                $rawRows[] = count($values) === count(ProductMaintenanceCsv::HEADERS)
                    ? [
                        'line_number' => $lineNumber,
                        'data' => array_combine(ProductMaintenanceCsv::HEADERS, $values),
                    ]
                    : [
                        'line_number' => $lineNumber,
                        'structural_error' => sprintf(
                            'Jumlah kolom tidak sesuai: ditemukan %d, seharusnya %d.',
                            count($values),
                            count(ProductMaintenanceCsv::HEADERS)
                        ),
                    ];

                if (count($rawRows) > ProductMaintenanceCsv::MAX_ROWS) {
                    throw new InvalidProductImportFile('File hanya boleh berisi maksimum 1.000 baris data.');
                }
            }
        } finally {
            fclose($stream);
        }

        if ($rawRows === []) {
            throw new InvalidProductImportFile('File tidak mempunyai baris maintenance untuk dipreview.');
        }

        return [$rawRows, $blankRows];
    }

    /** @param array<int, string> $header */
    private function validateHeader(array $header): void
    {
        if (count($header) !== count(array_unique($header))) {
            throw new InvalidProductImportFile('Header CSV tidak boleh mempunyai nama kolom duplikat.');
        }

        if ($header !== ProductMaintenanceCsv::HEADERS) {
            throw new InvalidProductImportFile(
                'Header CSV tidak sesuai kontrak maintenance. Unduh template terbaru dan pertahankan urutan kolom.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rawRow
     * @param  array<string, mixed>  $context
     * @param  array<int, int>  $seenProductIds
     * @return array<string, mixed>
     */
    private function previewRow(array $rawRow, array $context, array &$seenProductIds): array
    {
        if (isset($rawRow['structural_error'])) {
            return $this->errorRow($rawRow['line_number'], $rawRow['structural_error']);
        }

        $data = collect($rawRow['data'])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->all();
        $issues = [];
        $productId = $this->normalizeProductId($data['product_id'], $issues);
        $product = $productId ? $context['products']->get($productId) : null;

        if ($productId && isset($seenProductIds[$productId])) {
            $issues[] = $this->issue(
                'error',
                'product_id',
                'Product ID duplikat dengan baris '.$seenProductIds[$productId].'.'
            );
        } elseif ($productId) {
            $seenProductIds[$productId] = $rawRow['line_number'];
        }

        if ($productId && ! $product) {
            $issues[] = $this->issue('error', 'product_id', 'Produk tidak ditemukan. Ambil snapshot terbaru.');
        }

        $data['product_id'] = $productId ?? $data['product_id'];
        $data['expected_updated_at'] = $this->validateExpectedTimestamp(
            $data['expected_updated_at'],
            $product,
            $issues
        );
        $this->validateExpectedFingerprint($data['expected_row_fingerprint'], $product, $issues);
        $this->validateText($data, $issues);
        $this->normalizeAndValidateNumbers($data, $issues);
        $this->normalizeAndValidateControlledValues($data, $issues);
        $this->normalizeAndValidateTaxonomy($data, $context, $issues);
        $this->normalizeNotes($data);

        $currentSnapshot = $product ? $this->snapshot($product) : null;
        $changes = collect($issues)->contains('severity', 'error') || ! $product
            ? []
            : $this->changes($product, $data);

        if ($product && $changes === [] && ! collect($issues)->contains('severity', 'error')) {
            $issues[] = $this->issue(
                'review',
                'baris',
                'Tidak ada perubahan. Cell kosong mempertahankan nilai katalog saat ini.'
            );
        }

        $data['_changes'] = $changes;
        $status = collect($issues)->contains('severity', 'error')
            ? 'error'
            : (collect($issues)->contains('severity', 'review') ? 'review' : 'valid');

        return [
            'line_number' => $rawRow['line_number'],
            'status' => $status,
            'action' => $product ? 'update' : 'conflict',
            'data' => $data,
            'matched_product' => $product ? [
                'id' => $product->getKey(),
                'name' => $product->name,
                'publication_status' => $product->publication_status,
            ] : null,
            'issues' => $issues,
            'current_snapshot' => $currentSnapshot,
        ];
    }

    /** @param array<int, array<string, string>> $issues */
    private function normalizeProductId(string $value, array &$issues): ?int
    {
        if ($value === '' || ! ctype_digit($value) || (int) $value < 1) {
            $issues[] = $this->issue('error', 'product_id', 'Product ID wajib berupa bilangan bulat positif.');

            return null;
        }

        return (int) $value;
    }

    /** @param array<int, array<string, string>> $issues */
    private function validateExpectedTimestamp(string $value, ?Product $product, array &$issues): string
    {
        if ($value === '') {
            $issues[] = $this->issue('error', 'expected_updated_at', 'Timestamp snapshot wajib diisi.');

            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+\-]\d{2}:\d{2})$/', $value) !== 1) {
            $issues[] = $this->issue('error', 'expected_updated_at', 'Timestamp harus berformat ISO 8601.');

            return $value;
        }

        try {
            $expected = CarbonImmutable::parse($value);
        } catch (Throwable) {
            $issues[] = $this->issue('error', 'expected_updated_at', 'Timestamp harus berformat ISO 8601.');

            return $value;
        }

        $normalized = $expected->toIso8601String();

        if ($product && ! $expected->utc()->equalTo($product->updated_at?->toImmutable()->utc())) {
            $issues[] = $this->issue(
                'error',
                'expected_updated_at',
                'Produk berubah sejak snapshot dibuat. Ambil snapshot terbaru sebelum melanjutkan.'
            );
        }

        return $normalized;
    }

    /** @param array<int, array<string, string>> $issues */
    private function validateExpectedFingerprint(string $value, ?Product $product, array &$issues): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            $issues[] = $this->issue(
                'error',
                'expected_row_fingerprint',
                'Row fingerprint wajib disalin utuh dari snapshot katalog terbaru.'
            );

            return;
        }

        if ($product && ! hash_equals($this->rowFingerprint->hash($product), $value)) {
            $issues[] = $this->issue(
                'error',
                'expected_row_fingerprint',
                'Data produk atau offer berubah sejak snapshot dibuat. Ambil snapshot terbaru.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function validateText(array $data, array &$issues): void
    {
        if (mb_strlen($data['nama_produk']) > 255) {
            $issues[] = $this->issue('error', 'nama_produk', 'Nama produk maksimal 255 karakter.');
        }

        if (mb_strlen($data['deskripsi_produk']) > 20000) {
            $issues[] = $this->issue('error', 'deskripsi_produk', 'Deskripsi produk maksimal 20.000 karakter.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function normalizeAndValidateNumbers(array &$data, array &$issues): void
    {
        if ($data['harga'] !== '' && (! preg_match('/^\d+(?:\.\d{1,2})?$/', $data['harga'])
            || (float) $data['harga'] <= 0
            || (float) $data['harga'] > 99999999.99)) {
            $issues[] = $this->issue('error', 'harga', 'Harga harus angka positif tanpa pemisah ribuan.');
        } elseif ($data['harga'] !== '') {
            $data['harga'] = number_format((float) $data['harga'], 2, '.', '');
        }

        if ($data['stok_snapshot'] !== '' && (! ctype_digit($data['stok_snapshot'])
            || (int) $data['stok_snapshot'] > 999999)) {
            $issues[] = $this->issue('error', 'stok_snapshot', 'Stok harus bilangan bulat 0–999999.');
        } elseif ($data['stok_snapshot'] !== '') {
            $data['stok_snapshot'] = (int) $data['stok_snapshot'];
        }

        if ($data['ukuran_ml'] !== '' && (! ctype_digit($data['ukuran_ml'])
            || (int) $data['ukuran_ml'] < 1
            || (int) $data['ukuran_ml'] > 10000)) {
            $issues[] = $this->issue('error', 'ukuran_ml', 'Ukuran harus bilangan bulat 1–10000 ml.');
        } elseif ($data['ukuran_ml'] !== '') {
            $data['ukuran_ml'] = (int) $data['ukuran_ml'];
        }

        if (($data['harga'] === '') !== ($data['ukuran_ml'] === '')) {
            $issues[] = $this->issue(
                'error',
                'harga',
                'Harga dan ukuran wajib diisi bersama sebagai satu offer.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $issues
     */
    private function normalizeAndValidateControlledValues(array &$data, array &$issues): void
    {
        if ($data['gender'] !== '' && ! in_array($data['gender'], ['Unisex', 'Pria', 'Wanita'], true)) {
            $issues[] = $this->issue('error', 'gender', 'Gender harus Unisex, Pria, atau Wanita.');
        }

        if ($data['terlaris'] === '') {
            $data['terlaris'] = null;

            return;
        }

        $data['terlaris'] = match (mb_strtolower($data['terlaris'])) {
            '1', 'true', 'ya' => true,
            '0', 'false', 'tidak' => false,
            default => null,
        };

        if ($data['terlaris'] === null) {
            $issues[] = $this->issue('error', 'terlaris', 'Terlaris harus ya/tidak, true/false, atau 1/0.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, string>>  $issues
     */
    private function normalizeAndValidateTaxonomy(array &$data, array $context, array &$issues): void
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
                $issues[] = $this->issue('error', $field, $definition['label'].' tidak ditemukan di admin.');
            } elseif (! $taxonomy->is_active) {
                $issues[] = $this->issue('error', $field, $definition['label'].' sedang nonaktif.');
            } else {
                $data[$field] = $taxonomy->name;
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function normalizeNotes(array &$data): void
    {
        foreach (['top_notes', 'middle_notes', 'base_notes'] as $field) {
            $data[$field] = $data[$field] === ''
                ? []
                : collect(explode('|', $data[$field]))
                    ->map(fn (string $note): string => trim($note))
                    ->filter()
                    ->unique(fn (string $note): string => $this->key($note))
                    ->values()
                    ->all();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{field: string, label: string, current: mixed, proposed: mixed}>
     */
    private function changes(Product $product, array $data): array
    {
        $changes = [];
        $this->addChange($changes, 'nama_produk', 'Nama', $product->name, $data['nama_produk']);
        $this->addChange($changes, 'deskripsi_produk', 'Deskripsi', $product->description, $data['deskripsi_produk']);
        $this->addChange($changes, 'brand', 'Brand', $product->brand?->name, $data['brand']);
        $this->addChange($changes, 'gender', 'Gender', $product->gender, $data['gender']);
        $this->addChange($changes, 'stok_snapshot', 'Stok snapshot', $product->stock_quantity, $data['stok_snapshot']);

        if ($data['terlaris'] !== null && $product->is_best_seller !== $data['terlaris']) {
            $changes[] = $this->change(
                'terlaris',
                'Terlaris',
                $product->is_best_seller ? 'ya' : 'tidak',
                $data['terlaris'] ? 'ya' : 'tidak'
            );
        }

        $this->addChange($changes, 'kategori', 'Kategori', $product->category?->name, $data['kategori']);

        if ($data['harga'] !== '' && $data['ukuran_ml'] !== '') {
            $offer = $product->variants->where('is_active', true)->sortBy('id')->first();
            $currentPrice = $offer?->price === null ? null : number_format((float) $offer->price, 2, '.', '');

            if ($currentPrice !== $data['harga'] || $offer?->volume !== $data['ukuran_ml']) {
                $changes[] = $this->change(
                    'offer',
                    'Harga + ukuran',
                    $offer ? $currentPrice.' / '.$offer->volume.' ml' : 'Belum ada offer aktif',
                    $data['harga'].' / '.$data['ukuran_ml'].' ml'
                );
            }
        }

        $notes = $product->fragrance_notes ?? [];

        foreach (['top' => 'top_notes', 'middle' => 'middle_notes', 'base' => 'base_notes'] as $group => $field) {
            if ($data[$field] === []) {
                continue;
            }

            $current = array_values($notes[$group] ?? []);

            if ($current !== $data[$field]) {
                $changes[] = $this->change(
                    $field,
                    ucfirst($group).' notes',
                    implode(' | ', $current),
                    implode(' | ', $data[$field])
                );
            }
        }

        return $changes;
    }

    /** @param array<int, array<string, mixed>> $changes */
    private function addChange(
        array &$changes,
        string $field,
        string $label,
        mixed $current,
        mixed $proposed
    ): void {
        if ($proposed !== '' && $proposed !== null && $current !== $proposed) {
            $changes[] = $this->change($field, $label, $current, $proposed);
        }
    }

    /** @return array{field: string, label: string, current: mixed, proposed: mixed} */
    private function change(string $field, string $label, mixed $current, mixed $proposed): array
    {
        return compact('field', 'label', 'current', 'proposed');
    }

    /** @return array<string, mixed> */
    private function snapshot(Product $product): array
    {
        $offer = $product->variants->where('is_active', true)->sortBy('id')->first();

        return [
            'product' => [
                'id' => $product->getKey(),
                'updated_at' => $product->updated_at?->toIso8601String(),
                'brand_id' => $product->brand_id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'description' => $product->description,
                'fragrance_notes' => $product->fragrance_notes,
                'gender' => $product->gender,
                'stock_quantity' => $product->stock_quantity,
                'is_best_seller' => $product->is_best_seller,
                'publication_status' => $product->publication_status,
                'availability_status' => $product->availability_status,
            ],
            'offer' => $offer ? [
                'id' => $offer->getKey(),
                'volume' => $offer->volume,
                'price' => $offer->price,
                'is_active' => $offer->is_active,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function lookupContext(): array
    {
        $brands = Brand::query()->orderBy('id')->get();
        $categories = Category::query()->orderBy('id')->get();
        $products = Product::query()
            ->with([
                'brand:id,name',
                'category:id,name',
                'variants:id,product_id,volume,price,stock,is_active,updated_at',
            ])
            ->orderBy('id')
            ->get();

        $state = [
            'brands' => $brands->map(fn (Brand $brand): array => [
                $brand->id, $brand->name, $brand->is_active, $brand->updated_at?->toJSON(),
            ])->all(),
            'categories' => $categories->map(fn (Category $category): array => [
                $category->id, $category->name, $category->is_active, $category->updated_at?->toJSON(),
            ])->all(),
            'products' => $products->map(fn (Product $product): array => [
                $product->id,
                $product->updated_at?->toJSON(),
                $product->brand_id,
                $product->category_id,
                $product->name,
                $product->description,
                $product->fragrance_notes,
                $product->gender,
                $product->stock_quantity,
                $product->is_best_seller,
                $product->publication_status,
                $product->availability_status,
                $product->variants->map(fn ($variant): array => [
                    $variant->id,
                    $variant->volume,
                    $variant->price,
                    $variant->stock,
                    $variant->is_active,
                    $variant->updated_at?->toJSON(),
                ])->all(),
            ])->all(),
        ];

        return [
            'brands' => $brands->keyBy(fn (Brand $brand): string => $this->key($brand->name)),
            'categories' => $categories->keyBy(fn (Category $category): string => $this->key($category->name)),
            'products' => $products->keyBy('id'),
            'catalog_state_fingerprint' => hash(
                'sha256',
                json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function errorRow(int $lineNumber, string $message): array
    {
        return [
            'line_number' => $lineNumber,
            'status' => 'error',
            'action' => 'conflict',
            'data' => ['_changes' => []],
            'matched_product' => null,
            'issues' => [$this->issue('error', 'baris', $message)],
            'current_snapshot' => null,
        ];
    }

    /** @return array{severity: string, field: string, message: string} */
    private function issue(string $severity, string $field, string $message): array
    {
        return compact('severity', 'field', 'message');
    }

    private function removeBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
