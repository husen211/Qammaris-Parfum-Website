<?php

namespace Tests\Feature;

use App\Imports\Products\CanonicalProductCsv;
use App\Imports\Products\ProductMaintenanceCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ProductCatalogRowFingerprint;
use App\Services\ProductImportPayloadHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProductMaintenanceApplyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create(['name' => 'Admin Maintenance Apply']);
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_only_admin_with_confirmation_can_apply_current_maintenance_batch(): void
    {
        $product = $this->product('Auth');
        $batch = $this->preview([$this->row($product, ['nama_produk' => 'Nama Auth Baru'])]);

        auth()->logout();
        $this->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch))
            ->assertSessionHasErrors(['confirm_apply']);

        $providerBatch = $this->providerBatch();
        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $providerBatch), ['confirm_apply' => '1'])
            ->assertNotFound();

        $this->assertSame('Parfum Auth', $product->fresh()->name);
    }

    public function test_apply_updates_allowlisted_fields_and_preserves_protected_state(): void
    {
        $newBrand = Brand::create(['name' => 'Brand Baru', 'is_active' => true]);
        $newCategory = Category::create(['name' => 'Extrait', 'is_active' => true]);
        $product = $this->product('Lengkap');
        $product->forceFill([
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_source' => 'manual',
            'availability_checked_at' => now()->subHour(),
        ])->save();
        $product = $product->fresh();
        $slug = $product->slug;
        $availabilityCheckedAt = $product->availability_checked_at->toJSON();
        $batch = $this->preview([$this->row($product, [
            'nama_produk' => 'Parfum Lengkap Baru',
            'deskripsi_produk' => 'Deskripsi maintenance',
            'harga' => '650000',
            'brand' => $newBrand->name,
            'gender' => 'Wanita',
            'stok_snapshot' => '8',
            'terlaris' => 'ya',
            'kategori' => $newCategory->name,
            'ukuran_ml' => '50',
            'top_notes' => 'Lemon|Bergamot',
            'middle_notes' => 'Rose',
            'base_notes' => 'Amber|Musk',
        ])]);
        $before = $batch->rows->first()->before_snapshot;

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect(route('admin.product-maintenance.create', ['batch' => $batch->id]))
            ->assertSessionHas('success');

        $product->refresh();
        $offer = $product->variants()->where('is_active', true)->firstOrFail();
        $batch->refresh();
        $row = $batch->rows()->firstOrFail();

        $this->assertSame('Parfum Lengkap Baru', $product->name);
        $this->assertSame('Deskripsi maintenance', $product->description);
        $this->assertSame($newBrand->id, $product->brand_id);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertSame('Wanita', $product->gender);
        $this->assertSame(8, $product->stock_quantity);
        $this->assertTrue($product->is_best_seller);
        $this->assertSame(['top' => ['Lemon', 'Bergamot'], 'middle' => ['Rose'], 'base' => ['Amber', 'Musk']], $product->fragrance_notes);
        $this->assertSame('650000.00', $product->base_price);
        $this->assertSame('650000.00', $offer->price);
        $this->assertSame(50, $offer->volume);

        $this->assertSame($slug, $product->slug);
        $this->assertSame(Product::PUBLICATION_PUBLISHED, $product->publication_status);
        $this->assertSame(Product::AVAILABILITY_SOLD_OUT, $product->availability_status);
        $this->assertSame('manual', $product->availability_source);
        $this->assertSame($availabilityCheckedAt, $product->availability_checked_at->toJSON());
        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('product_external_identities', 0);

        $this->assertSame(ProductImportBatch::STATUS_APPLIED, $batch->status);
        $this->assertSame($this->admin->id, $batch->applied_by);
        $this->assertSame(1, $batch->applied_rows);
        $this->assertSame(0, $batch->blocked_rows);
        $this->assertSame(ProductImportRow::APPLY_UPDATED, $row->apply_status);
        $this->assertSame($product->id, $row->applied_product_id);
        $this->assertSame($before, $row->before_snapshot);
        $this->assertNotSame($row->before_snapshot, $row->after_snapshot);

        $this->actingAs($this->admin)
            ->get(route('admin.product-maintenance.create', ['batch' => $batch->id]))
            ->assertOk()
            ->assertSee('Apply maintenance selesai.')
            ->assertSee('Diterapkan')
            ->assertDontSee('name="confirm_apply"', false);
    }

    public function test_apply_skips_noop_blocks_errors_and_is_idempotent(): void
    {
        $validProduct = $this->product('Valid');
        $noopProduct = $this->product('Noop');
        $missingProduct = $this->product('Missing Source');
        $batch = $this->preview([
            $this->row($validProduct, ['nama_produk' => 'Valid Diperbarui']),
            $this->row($noopProduct),
            array_replace($this->row($missingProduct), ['product_id' => '999999']),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertSessionHas('success');

        $batch->refresh();
        $rows = $batch->rows()->orderBy('line_number')->get();
        $firstAppliedAt = $validProduct->fresh()->updated_at->toJSON();

        $this->assertSame(1, $batch->applied_rows);
        $this->assertSame(2, $batch->blocked_rows);
        $this->assertSame([
            ProductImportRow::APPLY_UPDATED,
            ProductImportRow::APPLY_SKIPPED_NO_CHANGES,
            ProductImportRow::APPLY_BLOCKED_ERROR,
        ], $rows->pluck('apply_status')->all());
        $this->assertSame('Valid Diperbarui', $validProduct->fresh()->name);
        $this->assertSame('Parfum Noop', $noopProduct->fresh()->name);

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertSessionHas('success');

        $this->assertSame($firstAppliedAt, $validProduct->fresh()->updated_at->toJSON());
        $this->assertSame(1, $batch->fresh()->applied_rows);
    }

    public function test_catalog_change_after_preview_marks_batch_stale_without_writes(): void
    {
        $target = $this->product('Target');
        $other = $this->product('Other');
        $batch = $this->preview([$this->row($target, ['nama_produk' => 'Target Baru'])]);
        $other->update(['name' => 'Other Berubah']);

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertSessionHas('error');

        $this->assertSame('Parfum Target', $target->fresh()->name);
        $this->assertSame(ProductImportBatch::STATUS_STALE, $batch->fresh()->status);
        $this->assertSame(ProductImportRow::APPLY_PENDING, $batch->rows()->firstOrFail()->apply_status);
    }

    public function test_tampered_payload_marks_batch_invalid_without_writes(): void
    {
        $product = $this->product('Payload');
        $batch = $this->preview([$this->row($product, ['nama_produk' => 'Payload Baru'])]);
        $row = $batch->rows()->firstOrFail();
        $data = $row->normalized_data;
        $data['nama_produk'] = 'Payload Disusupi';
        $row->update(['normalized_data' => $data]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertSessionHas('error');

        $this->assertSame('Parfum Payload', $product->fresh()->name);
        $this->assertSame(ProductImportBatch::STATUS_INVALID, $batch->fresh()->status);
    }

    public function test_unexpected_row_failure_rolls_back_every_catalog_write(): void
    {
        $first = $this->product('Rollback Satu');
        $second = $this->product('Rollback Dua');
        $batch = $this->preview([
            $this->row($first, ['nama_produk' => 'Seharusnya Tidak Tersimpan']),
            $this->row($second, ['nama_produk' => 'Pemicu Rollback']),
        ]);
        $row = $batch->rows()->orderBy('line_number')->get()->last();
        $data = $row->normalized_data;
        $data['_changes'][] = [
            'field' => 'field_tidak_dikenal',
            'label' => 'Invalid',
            'current' => null,
            'proposed' => 'invalid',
        ];
        $row->update([
            'normalized_data' => $data,
            'payload_hash' => app(ProductImportPayloadHasher::class)->hash($data, $row->issues, $row->candidate_action),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.apply', $batch), ['confirm_apply' => '1'])
            ->assertSessionHas('error', 'Apply maintenance gagal dan tidak ada perubahan katalog yang disimpan.');

        $this->assertSame('Parfum Rollback Satu', $first->fresh()->name);
        $this->assertSame('Parfum Rollback Dua', $second->fresh()->name);
        $this->assertSame(ProductImportBatch::STATUS_FAILED, $batch->fresh()->status);
        $this->assertTrue($batch->rows()->get()->every(
            fn (ProductImportRow $item): bool => $item->apply_status === ProductImportRow::APPLY_PENDING
        ));
    }

    /** @param array<int, array<string, string>> $rows */
    private function preview(array $rows): ProductImportBatch
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-maintenance.preview'), [
                'maintenance_file' => UploadedFile::fake()->createWithContent('maintenance-apply.csv', $this->csv($rows)),
            ])
            ->assertOk();

        return $response->viewData('batch');
    }

    private function product(string $suffix): Product
    {
        $brand = Brand::create(['name' => 'Brand '.$suffix, 'is_active' => true]);
        $category = Category::create(['name' => 'Kategori '.$suffix, 'is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Parfum '.$suffix,
            'slug' => 'parfum-'.str($suffix)->slug(),
            'description' => 'Deskripsi '.$suffix,
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
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ProductMaintenanceCsv::HEADERS, escape: '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(
                fn (string $header): string => (string) ($row[$header] ?? ''),
                ProductMaintenanceCsv::HEADERS
            ), escape: '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function providerBatch(): ProductImportBatch
    {
        return ProductImportBatch::create([
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
    }
}
