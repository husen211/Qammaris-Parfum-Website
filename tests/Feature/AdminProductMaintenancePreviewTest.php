<?php

namespace Tests\Feature;

use App\Imports\Products\CanonicalProductCsv;
use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\User;
use App\Services\ProductCatalogRowFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProductMaintenancePreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create(['name' => 'Admin Maintenance']);
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_only_admin_can_open_download_and_preview_maintenance(): void
    {
        $product = $this->product();
        $file = $this->file([$this->row($product, ['nama_produk' => 'Nama Baru'])]);

        $this->get(route('admin.product-maintenance.create'))->assertRedirect(route('login'));
        $this->get(route('admin.product-maintenance.template'))->assertRedirect(route('login'));
        $this->post(route('admin.product-maintenance.preview'), ['maintenance_file' => $file])
            ->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.product-maintenance.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.product-maintenance.template'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.product-maintenance.preview'), [
            'maintenance_file' => $this->file([$this->row($product)]),
        ])->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.product-maintenance.create'))
            ->assertOk()
            ->assertSee('Bulk maintenance katalog')
            ->assertSee('Apply hanya tersedia untuk batch preview yang masih fresh.')
            ->assertSee('Belum ada batch maintenance tersimpan.');

        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create'))
            ->assertOk()
            ->assertSee('Preview maintenance')
            ->assertSee(route('admin.product-maintenance.create'), false);
    }

    public function test_admin_can_download_exact_utf8_maintenance_template(): void
    {
        $content = $this->actingAs($this->admin)
            ->get(route('admin.product-maintenance.template'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('cache-control', 'no-store, private')
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $rows = $this->parseCsv($content);
        $this->assertSame(ProductMaintenanceCsv::HEADERS, $rows[0]);
        $this->assertSame(ProductMaintenanceCsv::EXAMPLE, $rows[1]);
    }

    public function test_valid_preview_is_persisted_idempotent_and_read_only(): void
    {
        $brand = Brand::create(['name' => 'Brand Baru', 'is_active' => true]);
        $category = Category::create(['name' => 'Extrait', 'is_active' => true]);
        $product = $this->product();
        $original = $product->fresh()->toArray();
        $offer = $product->variants()->first();
        $originalOffer = $offer->fresh()->toArray();
        $row = $this->row($product, [
            'nama_produk' => '<script>alert(1)</script>',
            'harga' => '650000',
            'brand' => $brand->name,
            'stok_snapshot' => '8',
            'terlaris' => 'ya',
            'kategori' => $category->name,
            'ukuran_ml' => '100',
            'top_notes' => 'Lemon|Bergamot',
        ]);
        $contents = $this->csv([$row]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => UploadedFile::fake()->createWithContent('maintenance.csv', $contents),
            ])
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Konfirmasi apply maintenance')
            ->assertSee('name="confirm_apply"', false);

        $preview = $response->viewData('preview');
        $batch = $response->viewData('batch');
        $this->assertSame(['total' => 1, 'valid' => 1, 'review' => 0, 'error' => 0], $preview['summary']);
        $this->assertSame(ProductMaintenanceCsv::VERSION, $batch->contract_version);
        $this->assertSame($product->id, $batch->rows->first()->matched_product_id);
        $this->assertNotNull($batch->rows->first()->before_snapshot);
        $this->assertSame(
            ['nama_produk', 'brand', 'stok_snapshot', 'terlaris', 'kategori', 'offer', 'top_notes'],
            collect($preview['rows'][0]['data']['_changes'])->pluck('field')->all()
        );

        $this->assertSame($original, $product->fresh()->toArray());
        $this->assertSame($originalOffer, $offer->fresh()->toArray());

        $repeat = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => UploadedFile::fake()->createWithContent('maintenance.csv', $contents),
            ])
            ->assertOk();

        $this->assertSame($batch->id, $repeat->viewData('batch')->id);
        $this->assertDatabaseCount('product_import_batches', 1);
        $this->assertDatabaseCount('product_import_rows', 1);
    }

    public function test_blank_fields_preserve_current_values_and_noop_requires_review(): void
    {
        $product = $this->product();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => $this->file([$this->row($product)]),
            ])
            ->assertOk();

        $row = $response->viewData('preview')['rows'][0];
        $this->assertSame('review', $row['status']);
        $this->assertSame([], $row['data']['_changes']);
        $this->assertStringContainsString('Tidak ada perubahan', $row['issues'][0]['message']);
        $this->assertSame('Parfum Existing', $product->fresh()->name);
    }

    public function test_row_fingerprint_detects_offer_change_even_when_product_timestamp_is_unchanged(): void
    {
        $product = $this->product();
        $row = $this->row($product, ['harga' => '650000', 'ukuran_ml' => '100']);
        $productUpdatedAt = $product->updated_at->toIso8601String();
        $product->variants()->firstOrFail()->update(['volume' => 50]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => $this->file([$row]),
            ])
            ->assertOk();

        $previewRow = $response->viewData('preview')['rows'][0];
        $this->assertSame($productUpdatedAt, $product->fresh()->updated_at->toIso8601String());
        $this->assertSame('error', $previewRow['status']);
        $this->assertTrue(collect($previewRow['issues'])->contains(
            fn (array $issue): bool => $issue['field'] === 'expected_row_fingerprint'
                && str_contains($issue['message'], 'produk atau offer berubah')
        ));
    }

    public function test_preview_blocks_stale_missing_duplicate_and_invalid_values(): void
    {
        $inactiveBrand = Brand::create(['name' => 'Brand Nonaktif', 'is_active' => false]);
        $product = $this->product();
        $rows = [
            $this->row($product, [
                'expected_updated_at' => '2020-01-01T00:00:00+00:00',
                'expected_row_fingerprint' => str_repeat('0', 64),
                'harga' => '500000',
            ]),
            $this->row($product, [
                'gender' => 'Semua',
                'stok_snapshot' => '-1',
                'brand' => $inactiveBrand->name,
            ]),
            array_replace($this->row($product), [
                'product_id' => '999999',
                'nama_produk' => 'Missing',
            ]),
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => $this->file($rows),
            ])
            ->assertOk();

        $preview = $response->viewData('preview');
        $this->assertSame(['total' => 3, 'valid' => 0, 'review' => 0, 'error' => 3], $preview['summary']);
        $messages = collect($preview['rows'])->flatMap(fn (array $row) => collect($row['issues'])->pluck('message'));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'berubah sejak snapshot')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'produk atau offer berubah')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'Harga dan ukuran wajib diisi bersama')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'Product ID duplikat')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'Gender harus')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'Stok harus')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'sedang nonaktif')));
        $this->assertTrue($messages->contains(fn (string $message): bool => str_contains($message, 'Produk tidak ditemukan')));
    }

    public function test_structural_failure_does_not_create_an_empty_batch(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => UploadedFile::fake()->createWithContent('invalid.csv', "wrong,header\n1,value"),
            ])
            ->assertOk()
            ->assertSee('Header CSV tidak sesuai kontrak maintenance.');

        $this->assertDatabaseCount('product_import_batches', 0);
        $this->assertDatabaseCount('product_import_rows', 0);
    }

    public function test_maintenance_and_provider_batches_are_isolated_by_contract(): void
    {
        $product = $this->product();
        $maintenanceResponse = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => $this->file([$this->row($product, ['nama_produk' => 'Nama Baru'])]),
            ])
            ->assertOk();
        $maintenanceBatch = $maintenanceResponse->viewData('batch');
        $providerBatch = ProductImportBatch::create([
            'actor_id' => $this->admin->id,
            'source_filename' => 'provider.csv',
            'source_size' => 10,
            'source_fingerprint' => str_repeat('a', 64),
            'contract_version' => CanonicalProductCsv::VERSION,
            'catalog_state_fingerprint' => str_repeat('b', 64),
            'idempotency_key' => str_repeat('c', 64),
            'status' => ProductImportBatch::STATUS_PREVIEWED,
            'total_rows' => 0,
            'valid_rows' => 0,
            'review_rows' => 0,
            'error_rows' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create', ['batch' => $maintenanceBatch->id]))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.report', $maintenanceBatch))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $maintenanceBatch), ['confirm_apply' => '1'])
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->get(route('admin.product-maintenance.create', ['batch' => $providerBatch->id]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.product-maintenance.create'))
            ->assertSee('maintenance.csv')
            ->assertDontSee('provider.csv');
        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create'))
            ->assertSee('provider.csv')
            ->assertDontSee('maintenance.csv');
    }

    private function product(): Product
    {
        $brand = Brand::create(['name' => 'Brand Existing', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP', 'is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Parfum Existing',
            'slug' => 'parfum-existing',
            'description' => 'Deskripsi existing',
            'fragrance_notes' => ['top' => ['Citrus'], 'middle' => ['Rose'], 'base' => ['Musk']],
            'gender' => 'Unisex',
            'stock_quantity' => 4,
            'is_best_seller' => false,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        $product->variants()->create([
            'volume' => 100,
            'price' => 500000,
            'stock' => 4,
            'is_active' => true,
        ]);

        return $product->fresh();
    }

    /** @param array<string, string> $overrides */
    private function row(Product $product, array $overrides = []): array
    {
        return array_replace(array_fill_keys(ProductMaintenanceCsv::HEADERS, ''), [
            'product_id' => (string) $product->id,
            'expected_updated_at' => $product->updated_at->toIso8601String(),
            'expected_row_fingerprint' => app(ProductCatalogRowFingerprint::class)->hash($product),
        ], $overrides);
    }

    /** @param array<int, array<string, string>> $rows */
    private function file(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('maintenance.csv', $this->csv($rows));
    }

    /** @param array<int, array<string, string>> $rows */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ProductMaintenanceCsv::HEADERS, escape: '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header): string => (string) ($row[$header] ?? ''), ProductMaintenanceCsv::HEADERS), escape: '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    /** @return array<int, array<int, string|null>> */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
