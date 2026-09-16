<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportRow;
use App\Models\User;
use App\Services\ProductImportBatchCsvReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductImportReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->create(['name' => 'Admin Audit']);
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_only_admin_can_download_an_existing_batch_report(): void
    {
        [$batch] = $this->auditBatch();
        $route = route('admin.product-imports.report', $batch);

        $this->get($route)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($route)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.product-imports.report', 999999))->assertNotFound();

        $this->actingAs($this->admin)
            ->get($route)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff');
    }

    public function test_report_has_fixed_utf8_contract_and_safe_operational_rows(): void
    {
        [$batch, $product] = $this->auditBatch();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.product-imports.report', $batch))
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringNotContainsString('https://images.example.test/private.jpg', $content);
        $this->assertStringNotContainsString('before_snapshot', $content);

        $rows = $this->parseCsv($content);
        $this->assertSame(ProductImportBatchCsvReport::HEADERS, $rows[0]);
        $this->assertCount(3, $rows);

        $first = array_combine($rows[0], $rows[1]);
        $this->assertSame(ProductImportBatchCsvReport::VERSION, $first['report_version']);
        $this->assertSame((string) $batch->id, $first['batch_id']);
        $this->assertSame("'=WEBSERVICE(\"https://evil.test\")", $first['nama_produk']);
        $this->assertSame(ProductImportRow::APPLY_BLOCKED_PROTECTED, $first['apply_status']);
        $this->assertSame((string) $product->id, $first['product_id']);
        $this->assertSame(Product::PUBLICATION_PUBLISHED, $first['publication_status']);
        $this->assertSame('resolved', $first['resolution_status']);
        $this->assertSame('name|offer', $first['resolution_fields']);
        $this->assertSame('1', $first['image_stored']);
        $this->assertSame('1', $first['image_failed_or_blocked']);
        $this->assertStringContainsString('review:nama_produk:Perlu keputusan admin.', $first['issues']);
        $this->assertSame('Admin Audit', $first['preview_actor']);
        $this->assertSame('Admin Audit', $first['applied_actor']);
        $this->assertSame('Admin Audit', $first['resolved_actor']);

        $second = array_combine($rows[0], $rows[2]);
        $this->assertSame('3', $second['line_number']);
        $this->assertSame(ProductImportRow::APPLY_CREATED, $second['apply_status']);
        $this->assertSame('Normal Product', $second['nama_produk']);
    }

    public function test_batch_detail_and_history_expose_report_download(): void
    {
        [$batch] = $this->auditBatch();

        $this->actingAs($this->admin)
            ->get(route('admin.product-imports.create', ['batch' => $batch->id]))
            ->assertOk()
            ->assertSee('Unduh laporan batch CSV')
            ->assertSee('Unduh CSV')
            ->assertSee(route('admin.product-imports.report', $batch), false);
    }

    /** @return array{ProductImportBatch, Product} */
    private function auditBatch(): array
    {
        $product = Product::create([
            'name' => 'Protected Audit Product',
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'is_active' => true,
        ]);
        $batch = ProductImportBatch::create([
            'actor_id' => $this->admin->id,
            'applied_by' => $this->admin->id,
            'source_filename' => '=audit.csv',
            'source_size' => 512,
            'source_fingerprint' => hash('sha256', 'report-source'),
            'contract_version' => 'qammaris-products-v1',
            'catalog_state_fingerprint' => hash('sha256', 'report-state'),
            'idempotency_key' => hash('sha256', 'report-idempotency'),
            'status' => ProductImportBatch::STATUS_APPLIED,
            'applied_at' => now(),
            'total_rows' => 2,
            'valid_rows' => 1,
            'review_rows' => 1,
            'error_rows' => 0,
            'applied_rows' => 1,
            'blocked_rows' => 1,
            'skipped_blank_rows' => [],
        ]);
        $batch->rows()->create([
            'line_number' => 2,
            'status' => 'review',
            'candidate_action' => 'update',
            'provider' => 'shopee',
            'external_product_id' => '-000123',
            'matched_product_id' => $product->id,
            'normalized_data' => [
                'nama_produk' => '=WEBSERVICE("https://evil.test")',
                'foto_utama_url' => 'https://images.example.test/private.jpg',
            ],
            'issues' => [[
                'severity' => 'review',
                'field' => 'nama_produk',
                'message' => 'Perlu keputusan admin.',
            ]],
            'payload_hash' => str_repeat('a', 64),
            'apply_status' => ProductImportRow::APPLY_BLOCKED_PROTECTED,
            'applied_product_id' => $product->id,
            'apply_message' => 'Produk published ditahan.',
            'before_snapshot' => ['product' => ['id' => $product->id]],
            'after_snapshot' => ['product' => ['id' => $product->id]],
            'applied_at' => now(),
            'image_acquisition_status' => ProductImportRow::IMAGE_COMPLETED_WITH_ERRORS,
            'image_acquisition_outcomes' => [
                ['status' => 'stored', 'source_url' => 'https://images.example.test/private.jpg'],
                ['status' => 'blocked', 'message' => 'Host ditolak.'],
            ],
            'resolution_status' => 'resolved',
            'resolution_fields' => ['name', 'offer'],
            'resolved_by' => $this->admin->id,
            'resolved_at' => now(),
            'resolution_message' => 'Field terpilih diterapkan.',
            'resolution_before_snapshot' => ['secret' => 'before_snapshot'],
            'resolution_after_snapshot' => ['secret' => 'after_snapshot'],
        ]);
        $draft = Product::create([
            'name' => 'Normal Product',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'is_active' => false,
        ]);
        $batch->rows()->create([
            'line_number' => 3,
            'status' => 'valid',
            'candidate_action' => 'create',
            'provider' => 'shopee',
            'external_product_id' => 'NORMAL-1',
            'normalized_data' => ['nama_produk' => 'Normal Product'],
            'issues' => [],
            'payload_hash' => str_repeat('b', 64),
            'apply_status' => ProductImportRow::APPLY_CREATED,
            'applied_product_id' => $draft->id,
            'apply_message' => 'Draft baru dibuat.',
            'after_snapshot' => ['product' => ['id' => $draft->id]],
            'applied_at' => now(),
        ]);

        return [$batch, $product];
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
