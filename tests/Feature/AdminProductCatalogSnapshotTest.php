<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductCatalogSnapshotCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductCatalogSnapshotTest extends TestCase
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

    public function test_only_admin_can_download_catalog_snapshot(): void
    {
        $route = route('admin.product-imports.catalog-snapshot');

        $this->get($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($route)->assertForbidden();

        $this->actingAs($this->admin)
            ->get($route)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('cache-control', 'no-store, private');
    }

    public function test_snapshot_has_fixed_safe_contract_and_one_row_per_product_in_id_order(): void
    {
        $brand = Brand::create(['name' => '+Formula Brand', 'is_active' => true]);
        $category = Category::create(['name' => 'Extrait', 'is_active' => true]);
        $published = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => '=SUM(1,1)',
            'slug' => 'published-snapshot',
            'description' => '@SUM(1+1)',
            'fragrance_notes' => [
                'top' => ['-Citrus', 'Bergamot'],
                'middle' => ['Rose'],
                'base' => ['Musk'],
            ],
            'gender' => 'Unisex',
            'is_best_seller' => true,
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_source' => 'manual',
            'availability_checked_at' => now(),
            'stock_quantity' => 7,
        ]);
        $published->variants()->create([
            'volume' => 100,
            'price' => 345000,
            'stock' => 7,
            'is_active' => true,
        ]);
        $published->externalIdentities()->create([
            'provider' => 'shopee',
            'external_product_id' => 'SHOP-002',
        ]);
        $published->externalIdentities()->create([
            'provider' => 'majoo',
            'external_product_id' => 'MAJ-001',
        ]);
        ProductImage::create([
            'product_id' => $published->id,
            'image_path' => 'products/private-primary.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        ProductImage::create([
            'product_id' => $published->id,
            'image_path' => 'products/private-secondary.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);
        ProductImage::create([
            'product_id' => $published->id,
            'image_path' => 'products/private-archived.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ])->delete();

        $draft = Product::create([
            'name' => 'Draft Snapshot',
            'slug' => 'draft-snapshot',
            'is_active' => false,
            'publication_status' => Product::PUBLICATION_DRAFT,
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
        ]);
        $draft->variants()->create([
            'volume' => 50,
            'price' => 200000,
            'stock' => 0,
            'is_active' => false,
        ]);

        $archived = Product::create([
            'name' => 'Archived Snapshot',
            'slug' => 'archived-snapshot',
            'is_active' => false,
            'publication_status' => Product::PUBLICATION_ARCHIVED,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.product-imports.catalog-snapshot'))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringNotContainsString('products/private-primary.jpg', $content);

        $rows = $this->parseCsv($content);
        $this->assertSame(ProductCatalogSnapshotCsv::HEADERS, $rows[0]);
        $this->assertCount(4, $rows);

        $first = array_combine($rows[0], $rows[1]);
        $this->assertSame(ProductCatalogSnapshotCsv::VERSION, $first['snapshot_version']);
        $this->assertSame((string) $published->id, $first['product_id']);
        $this->assertSame("'=SUM(1,1)", $first['nama_produk']);
        $this->assertSame("'@SUM(1+1)", $first['deskripsi_produk']);
        $this->assertSame("'+Formula Brand", $first['brand']);
        $this->assertSame(Product::PUBLICATION_PUBLISHED, $first['publication_status']);
        $this->assertSame(Product::AVAILABILITY_AVAILABLE, $first['availability_effective']);
        $this->assertSame('majoo:MAJ-001|shopee:SHOP-002', $first['external_identities']);
        $this->assertSame('345000.00', $first['harga']);
        $this->assertSame('100', $first['ukuran_ml']);
        $this->assertSame("'-Citrus|Bergamot", $first['top_notes']);
        $this->assertSame('2', $first['active_image_count']);
        $this->assertSame('ya', $first['has_primary_image']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['row_fingerprint']);

        $second = array_combine($rows[0], $rows[2]);
        $this->assertSame((string) $draft->id, $second['product_id']);
        $this->assertSame(Product::PUBLICATION_DRAFT, $second['publication_status']);
        $this->assertSame('', $second['harga']);
        $this->assertSame('', $second['ukuran_ml']);

        $third = array_combine($rows[0], $rows[3]);
        $this->assertSame((string) $archived->id, $third['product_id']);
        $this->assertSame(Product::PUBLICATION_ARCHIVED, $third['publication_status']);
    }

    public function test_empty_catalog_snapshot_contains_only_the_header(): void
    {
        $content = $this->actingAs($this->admin)
            ->get(route('admin.product-imports.catalog-snapshot'))
            ->assertOk()
            ->streamedContent();

        $rows = $this->parseCsv($content);
        $this->assertSame([ProductCatalogSnapshotCsv::HEADERS], $rows);
    }

    public function test_import_page_explains_read_only_snapshot_and_links_to_it(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create'))
            ->assertOk()
            ->assertSee('Unduh snapshot katalog')
            ->assertSee('Snapshot bersifat read-only dan tidak dapat langsung diimport.')
            ->assertSee(route('admin.product-imports.catalog-snapshot'), false);
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
