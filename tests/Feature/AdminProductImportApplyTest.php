<?php

namespace Tests\Feature;

use App\Imports\Products\CanonicalProductCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductImportPayloadHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProductImportApplyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_only_admin_with_explicit_confirmation_can_apply_a_batch(): void
    {
        $batch = $this->preview([$this->completeRow()]);
        $this->app['auth']->guard()->logout();

        $this->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch))
            ->assertRedirect(route('admin.product-imports.create', ['batch' => $batch->id]))
            ->assertSessionHasErrors(['confirm_apply']);

        $this->assertDatabaseCount('products', 0);
        $this->assertSame(ProductImportBatch::STATUS_PREVIEWED, $batch->fresh()->status);
    }

    public function test_apply_creates_an_inactive_draft_identity_and_offer_without_media(): void
    {
        $brand = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $category = Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $batch = $this->preview([$this->completeRow()]);

        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create', ['batch' => $batch->id]))
            ->assertOk()
            ->assertSee('Apply batch #'.$batch->id)
            ->assertSee('Hasil apply');

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect(route('admin.product-imports.create', ['batch' => $batch->id]))
            ->assertSessionHas('success', 'Batch #'.$batch->id.' selesai: 1 baris diterapkan dan 0 baris ditahan.');

        $product = Product::sole();
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Preview Product', $product->name);
        $this->assertSame(Product::PUBLICATION_DRAFT, $product->publication_status);
        $this->assertFalse($product->is_active);
        $this->assertSame(Product::AVAILABILITY_UNKNOWN, $product->availability_status);
        $this->assertSame(5, $product->stock_quantity);
        $this->assertSame('import:shopee', $product->availability_source);
        $this->assertSame(['top' => ['Bergamot', 'Lemon'], 'middle' => ['Lavender'], 'base' => ['Musk', 'Amber']], $product->fragrance_notes);

        $offer = ProductVariant::sole();
        $this->assertSame($product->id, $offer->product_id);
        $this->assertSame(100, $offer->volume);
        $this->assertSame('599000.00', $offer->price);
        $this->assertSame(5, $offer->stock);

        $identity = ProductExternalIdentity::sole();
        $this->assertSame($product->id, $identity->product_id);
        $this->assertSame('58161979155', $identity->external_product_id);
        $this->assertDatabaseCount('product_images', 0);

        $batch->refresh();
        $this->assertSame(ProductImportBatch::STATUS_APPLIED, $batch->status);
        $this->assertSame($this->admin->id, $batch->applied_by);
        $this->assertSame(1, $batch->applied_rows);
        $this->assertSame(0, $batch->blocked_rows);

        $row = ProductImportRow::sole();
        $this->assertSame(ProductImportRow::APPLY_CREATED, $row->apply_status);
        $this->assertSame($product->id, $row->applied_product_id);
        $this->assertNull($row->before_snapshot);
        $this->assertSame(Product::PUBLICATION_DRAFT, $row->after_snapshot['product']['publication_status']);

        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create', ['batch' => $batch->id]))
            ->assertOk()
            ->assertSee('Batch sudah selesai diterapkan.')
            ->assertSee('Draft dibuat')
            ->assertSee('Buka draft #'.$product->id);
    }

    public function test_repeated_apply_is_idempotent(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => true]);
        Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $batch = $this->preview([$this->completeRow()]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect();

        $firstProductId = Product::sole()->id;

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('product_external_identities', 1);
        $this->assertSame($firstProductId, Product::sole()->id);
    }

    public function test_existing_draft_update_preserves_blank_fields_and_slug(): void
    {
        $brand = Brand::create(['name' => 'Existing Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'Existing Category', 'is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Existing Draft',
            'description' => 'Deskripsi existing.',
            'fragrance_notes' => [
                'top' => ['Old Top'],
                'middle' => ['Old Middle'],
                'base' => ['Old Base'],
            ],
            'gender' => 'Pria',
            'is_best_seller' => false,
            'is_active' => false,
            'publication_status' => Product::PUBLICATION_DRAFT,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'stock_quantity' => 7,
        ]);
        $originalSlug = $product->slug;
        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 30,
            'price' => 100000,
            'stock' => 7,
            'is_active' => true,
        ]);
        $product->externalIdentities()->create([
            'provider' => ProductExternalIdentity::PROVIDER_SHOPEE,
            'external_product_id' => '58161979155',
        ]);

        $row = $this->completeRow();
        $row['nama_produk'] = 'Existing Draft Updated';
        $row['deskripsi_produk'] = '';
        $row['brand'] = '';
        $row['gender'] = '';
        $row['stok'] = '';
        $row['terlaris'] = 'ya';
        $row['kategori'] = '';
        $row['ukuran_ml'] = '50';
        $row['harga'] = '125000';
        $row['top_notes'] = 'New Top';
        $row['middle_notes'] = '';
        $row['base_notes'] = '';
        $batch = $this->preview([$row]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('Existing Draft Updated', $product->name);
        $this->assertSame($originalSlug, $product->slug);
        $this->assertSame('Deskripsi existing.', $product->description);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Pria', $product->gender);
        $this->assertSame(7, $product->stock_quantity);
        $this->assertTrue($product->is_best_seller);
        $this->assertSame([
            'top' => ['New Top'],
            'middle' => ['Old Middle'],
            'base' => ['Old Base'],
        ], $product->fragrance_notes);
        $this->assertSame(Product::PUBLICATION_DRAFT, $product->publication_status);

        $offer = ProductVariant::sole();
        $this->assertSame(50, $offer->volume);
        $this->assertSame('125000.00', $offer->price);
        $this->assertSame(7, $offer->stock);
        $this->assertSame(ProductImportRow::APPLY_UPDATED, ProductImportRow::sole()->apply_status);
    }

    public function test_published_archived_and_error_rows_are_blocked_without_catalog_changes(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => true]);
        Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $published = $this->mappedProduct('Published Original', 'PUB-1', Product::PUBLICATION_PUBLISHED, true);
        $archived = $this->mappedProduct('Archived Original', 'ARC-1', Product::PUBLICATION_ARCHIVED, false);

        $publishedRow = $this->completeRow();
        $publishedRow['kode_produk'] = 'PUB-1';
        $publishedRow['nama_produk'] = 'Published Changed';
        $archivedRow = $this->completeRow();
        $archivedRow['kode_produk'] = 'ARC-1';
        $archivedRow['nama_produk'] = 'Archived Changed';
        $errorRow = $this->completeRow();
        $errorRow['kode_produk'] = 'ERR-1';
        $errorRow['harga'] = '599.000';
        $batch = $this->preview([$publishedRow, $archivedRow, $errorRow]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect();

        $this->assertSame('Published Original', $published->fresh()->name);
        $this->assertSame('Archived Original', $archived->fresh()->name);
        $this->assertDatabaseCount('products', 2);
        $this->assertSame(0, $batch->fresh()->applied_rows);
        $this->assertSame(3, $batch->fresh()->blocked_rows);
        $this->assertSame([
            ProductImportRow::APPLY_BLOCKED_PROTECTED,
            ProductImportRow::APPLY_BLOCKED_PROTECTED,
            ProductImportRow::APPLY_BLOCKED_ERROR,
        ], ProductImportRow::query()->orderBy('line_number')->pluck('apply_status')->all());
    }

    public function test_stale_catalog_state_aborts_before_catalog_mutation(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => true]);
        Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $batch = $this->preview([$this->completeRow()]);

        Category::create(['name' => 'Catalog State Changed', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Katalog berubah setelah preview. Upload ulang file untuk membuat preview baru.');

        $this->assertDatabaseCount('products', 0);
        $this->assertSame(ProductImportBatch::STATUS_STALE, $batch->fresh()->status);
        $this->assertSame(ProductImportRow::APPLY_PENDING, ProductImportRow::sole()->apply_status);
    }

    public function test_payload_hash_mismatch_marks_batch_invalid_without_catalog_mutation(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => true]);
        Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $batch = $this->preview([$this->completeRow()]);
        ProductImportRow::query()->update(['payload_hash' => str_repeat('0', 64)]);

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Versi kontrak atau payload batch tidak lagi valid. Buat preview baru.');

        $this->assertDatabaseCount('products', 0);
        $this->assertSame(ProductImportBatch::STATUS_INVALID, $batch->fresh()->status);
    }

    public function test_unexpected_row_failure_rolls_back_the_whole_batch(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => true]);
        Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $second = $this->completeRow();
        $second['kode_produk'] = 'SECOND-ROW';
        $second['nama_produk'] = 'Second Product';
        $batch = $this->preview([$this->completeRow(), $second]);

        $row = ProductImportRow::query()->orderByDesc('line_number')->firstOrFail();
        $row->candidate_action = 'unknown';
        $row->payload_hash = app(ProductImportPayloadHasher::class)->hash(
            $row->normalized_data,
            $row->issues,
            $row->candidate_action
        );
        $row->save();

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.apply', $batch), ['confirm_apply' => '1'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Apply gagal dan tidak ada perubahan katalog yang disimpan.');

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('product_external_identities', 0);
        $this->assertSame(ProductImportBatch::STATUS_FAILED, $batch->fresh()->status);
        $this->assertSame([
            ProductImportRow::APPLY_PENDING,
            ProductImportRow::APPLY_PENDING,
        ], ProductImportRow::query()->orderBy('line_number')->pluck('apply_status')->all());

        $successor = $this->preview([$this->completeRow(), $second]);
        $this->assertNotSame($batch->id, $successor->id);
        $this->assertSame(ProductImportBatch::STATUS_PREVIEWED, $successor->status);
        $this->assertDatabaseCount('product_import_batches', 2);
        $this->assertDatabaseCount('product_import_rows', 4);
    }

    private function preview(array $rows): ProductImportBatch
    {
        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => UploadedFile::fake()->createWithContent(
                    'products.csv',
                    $this->csvContent($rows)
                ),
            ])
            ->assertOk();

        return ProductImportBatch::query()->latest('id')->firstOrFail();
    }

    private function mappedProduct(string $name, string $code, string $status, bool $active): Product
    {
        $product = Product::create([
            'name' => $name,
            'publication_status' => $status,
            'is_active' => $active,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        $product->externalIdentities()->create([
            'provider' => ProductExternalIdentity::PROVIDER_SHOPEE,
            'external_product_id' => $code,
        ]);

        return $product;
    }

    /**
     * @return array<string, string>
     */
    private function completeRow(): array
    {
        return [
            'provider' => 'shopee',
            'kode_produk' => '58161979155',
            'nama_produk' => 'Preview Product',
            'deskripsi_produk' => 'Deskripsi produk hasil kurasi.',
            'harga' => '599000',
            'brand' => 'Afnan',
            'gender' => 'Unisex',
            'stok' => '5',
            'terlaris' => 'tidak',
            'kategori' => 'Eau de Parfum (EDP)',
            'ukuran_ml' => '100',
            'top_notes' => 'Bergamot|Lemon',
            'middle_notes' => 'Lavender',
            'base_notes' => 'Musk|Amber',
            'foto_utama_url' => 'https://example.com/primary.jpg',
            'foto_2_url' => '',
            'foto_3_url' => '',
        ];
    }

    private function csvContent(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, CanonicalProductCsv::HEADERS, escape: '');

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                array_map(fn (string $header): string => $row[$header] ?? '', CanonicalProductCsv::HEADERS),
                escape: ''
            );
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
