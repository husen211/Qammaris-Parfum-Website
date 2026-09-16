<?php

namespace Tests\Feature;

use App\Imports\Products\CanonicalProductCsv;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProductImportPreviewTest extends TestCase
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

    public function test_only_admin_can_open_product_import_preview(): void
    {
        $this->get(route('admin.product-imports.create'))->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)
            ->get(route('admin.product-imports.create'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create'))
            ->assertOk()
            ->assertSee('Preview saja · belum menulis data')
            ->assertSee('Unduh template CSV');
    }

    public function test_admin_can_download_the_exact_utf8_csv_contract(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.product-imports.template'))
            ->assertOk()
            ->assertDownload('template-import-produk-qammaris.csv');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = preg_split('/\R/', trim(substr($content, 3)));
        $this->assertSame(CanonicalProductCsv::HEADERS, str_getcsv($lines[0], escape: ''));
        $this->assertSame(CanonicalProductCsv::EXAMPLE, str_getcsv($lines[1], escape: ''));
    }

    public function test_valid_preview_is_read_only_and_reports_create_candidate(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => true]);
        Category::create(['name' => 'Eau de Parfum (EDP)', 'is_active' => true]);
        $countsBefore = $this->dataCounts();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$this->completeRow()]),
            ])
            ->assertOk()
            ->assertSee('File berhasil dibaca')
            ->assertSee('Preview Product')
            ->assertSee('Buat draft')
            ->assertSee('Tidak ada masalah.')
            ->assertSee('SHA-256:');

        $preview = $response->viewData('preview');
        $this->assertSame(['total' => 1, 'valid' => 1, 'review' => 0, 'error' => 0], $preview['summary']);
        $this->assertSame('valid', $preview['rows'][0]['status']);
        $this->assertSame('create', $preview['rows'][0]['action']);
        $this->assertSame(['Bergamot', 'Lemon'], $preview['rows'][0]['data']['top_notes']);
        $this->assertSame($countsBefore, $this->dataCounts());
    }

    public function test_preview_marks_incomplete_draft_data_for_review_without_inventing_values(): void
    {
        $row = $this->completeRow();
        foreach (['deskripsi_produk', 'harga', 'brand', 'gender', 'kategori', 'ukuran_ml', 'top_notes', 'middle_notes', 'base_notes', 'foto_utama_url'] as $field) {
            $row[$field] = '';
        }

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$row]),
            ])
            ->assertOk()
            ->assertSee('Perlu review')
            ->assertSee('Field ini masih kosong dan perlu dilengkapi sebelum publish.')
            ->assertSee('Belum ada URL gambar sumber untuk direview.');

        $preview = $response->viewData('preview');
        $this->assertSame(1, $preview['summary']['review']);
        $this->assertSame('', $preview['rows'][0]['data']['harga']);
        $this->assertSame([], $preview['rows'][0]['data']['top_notes']);
    }

    public function test_existing_identity_is_an_update_and_duplicate_identity_in_file_is_an_error(): void
    {
        $product = Product::create([
            'name' => 'Mapped Product',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);
        $product->externalIdentities()->create([
            'provider' => ProductExternalIdentity::PROVIDER_SHOPEE,
            'external_product_id' => '58161979155',
        ]);

        $row = $this->completeRow();
        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$row, $row]),
            ])
            ->assertOk()
            ->assertSee('Update draft')
            ->assertSee('#'.$product->id.' Mapped Product')
            ->assertSee('Provider dan kode produk duplikat dengan baris 2.');

        $preview = $response->viewData('preview');
        $this->assertSame('update', $preview['rows'][0]['action']);
        $this->assertSame('conflict', $preview['rows'][1]['action']);
        $this->assertSame('error', $preview['rows'][1]['status']);
    }

    public function test_same_name_without_external_identity_is_review_not_automatic_update(): void
    {
        Product::create([
            'name' => 'Preview Product',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$this->completeRow()]),
            ])
            ->assertOk()
            ->assertSee('Nama produk sudah ada, tetapi tidak mempunyai mapping provider+kode ini.')
            ->assertSee('Buat draft');

        $preview = $response->viewData('preview');
        $this->assertSame('create', $preview['rows'][0]['action']);
        $this->assertSame('review', $preview['rows'][0]['status']);
    }

    public function test_taxonomy_matching_is_case_insensitive_and_never_creates_missing_or_inactive_records(): void
    {
        Brand::create(['name' => 'Afnan', 'is_active' => false]);
        $row = $this->completeRow();
        $row['brand'] = 'afnan';
        $row['kategori'] = 'Kategori Belum Ada';
        $taxonomyCountBefore = Brand::count() + Category::count();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$row]),
            ])
            ->assertOk()
            ->assertSee('Brand ditemukan tetapi sedang nonaktif.')
            ->assertSee('Kategori belum ditemukan di admin.');

        $preview = $response->viewData('preview');
        $this->assertSame('review', $preview['rows'][0]['status']);
        $this->assertSame($taxonomyCountBefore, Brand::count() + Category::count());
    }

    public function test_invalid_values_and_untrusted_text_are_reported_and_escaped(): void
    {
        $row = $this->completeRow();
        $row['provider'] = 'marketplace-lain';
        $row['nama_produk'] = '<script>alert(1)</script>';
        $row['harga'] = '599.000';
        $row['gender'] = 'Semua';
        $row['stok'] = '-1';
        $row['terlaris'] = 'mungkin';
        $row['ukuran_ml'] = '0';
        $row['foto_utama_url'] = 'http://example.com/image.jpg';

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$row]),
            ])
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Provider hanya boleh shopee atau majoo.')
            ->assertSee('Harga harus angka positif tanpa pemisah ribuan.')
            ->assertSee('URL gambar harus HTTPS valid');

        $this->assertSame('error', $response->viewData('preview')['rows'][0]['status']);
    }

    public function test_structural_csv_errors_are_rejected_or_reported_safely(): void
    {
        $wrongHeader = CanonicalProductCsv::HEADERS;
        $wrongHeader[0] = 'marketplace';

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$this->completeRow()], $wrongHeader),
            ])
            ->assertOk()
            ->assertViewHas('errors', fn ($errors): bool => $errors->has('product_file'))
            ->assertSee('Header CSV tidak sesuai kontrak Qammaris.');

        $duplicateHeader = CanonicalProductCsv::HEADERS;
        $duplicateHeader[1] = 'provider';

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([$this->completeRow()], $duplicateHeader),
            ])
            ->assertOk()
            ->assertViewHas('errors', fn ($errors): bool => $errors->has('product_file'))
            ->assertSee('Header CSV tidak boleh mempunyai nama kolom duplikat.');

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload([]),
            ])
            ->assertOk()
            ->assertViewHas('errors', fn ($errors): bool => $errors->has('product_file'))
            ->assertSee('File tidak mempunyai baris produk untuk dipreview.');

        $content = $this->csvContent([$this->completeRow()]);
        $content .= "shopee,hanya-dua-kolom\n";

        $response = $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => UploadedFile::fake()->createWithContent('products.csv', $content),
            ])
            ->assertOk()
            ->assertSee('Jumlah kolom tidak sesuai');

        $this->assertSame(1, $response->viewData('preview')['summary']['error']);
    }

    public function test_non_utf8_too_many_rows_and_oversized_file_are_rejected(): void
    {
        $nonUtf8 = "provider,kode_produk,nama_produk\nshopee,1,\xC3\x28";
        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => UploadedFile::fake()->createWithContent('products.csv', $nonUtf8),
            ])
            ->assertOk()
            ->assertViewHas('errors', fn ($errors): bool => $errors->has('product_file'))
            ->assertSee('File harus menggunakan encoding UTF-8.');

        $rows = array_fill(0, CanonicalProductCsv::MAX_ROWS + 1, $this->completeRow());
        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => $this->csvUpload($rows),
            ])
            ->assertOk()
            ->assertViewHas('errors', fn ($errors): bool => $errors->has('product_file'))
            ->assertSee('maksimum 1.000 baris data');

        $this->actingAs($this->admin)
            ->post(route('admin.product-imports.preview'), [
                'product_file' => UploadedFile::fake()->create('large.csv', 5121, 'text/csv'),
            ])
            ->assertSessionHasErrors(['product_file']);
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

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<int, string>|null  $headers
     */
    private function csvUpload(array $rows, ?array $headers = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'products.csv',
            $this->csvContent($rows, $headers)
        );
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<int, string>|null  $headers
     */
    private function csvContent(array $rows, ?array $headers = null): string
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers ?? CanonicalProductCsv::HEADERS, escape: '');

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

    /**
     * @return array<string, int>
     */
    private function dataCounts(): array
    {
        return [
            'products' => Product::count(),
            'offers' => \DB::table('product_variants')->count(),
            'identities' => ProductExternalIdentity::count(),
            'images' => \DB::table('product_images')->count(),
        ];
    }
}
