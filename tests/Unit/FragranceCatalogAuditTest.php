<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FragranceCatalogAuditTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/qammaris-audit-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (['snapshot.json', 'catalog-audit.json', 'product-index.csv'] as $name) {
            if (is_file($this->directory.'/'.$name)) {
                unlink($this->directory.'/'.$name);
            }
        }
        rmdir($this->directory);
    }

    private function snapshot(): array
    {
        $row = ['id' => 1, 'name' => '=FAKE()', 'slug' => 'synthetic-only', 'brand' => '+Test Brand',
            'gender' => 'Unisex', 'description' => 'Eau de Parfum; vanilla notes. No measured performance.',
            'notes' => ['top' => ['Vanila', 'Vanilla'], 'middle' => ['Musk'], 'base' => ['TBC']],
            'active_offer_count' => 1, 'price' => '200000.00', 'size_ml' => 50, 'availability' => 'sold_out'];

        return ['schema' => 'qammaris-public-fragrance-audit-v1', 'label' => 'synthetic-local-test',
            'captured_at' => '2026-10-09T00:00:00Z', 'revision' => null, 'legacy_input_sha256' => null,
            'catalog_sha256' => hash('sha256', json_encode([$row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'products' => [$row], 'legacy_baselines' => []];
    }

    private function runAudit(array $snapshot): int
    {
        file_put_contents($this->directory.'/snapshot.json', json_encode($snapshot));
        $process = proc_open([PHP_BINARY, __DIR__.'/../../tools/fragrance-quiz/audit.php',
            '--snapshot='.$this->directory.'/snapshot.json', '--output='.$this->directory],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    public function test_offline_audit_preserves_input_and_sanitizes_spreadsheet_cells(): void
    {
        $snapshot = $this->snapshot();
        $this->assertSame(0, $this->runAudit($snapshot));
        $this->assertSame($snapshot, json_decode(file_get_contents($this->directory.'/snapshot.json'), true));
        $report = json_decode(file_get_contents($this->directory.'/catalog-audit.json'), true);
        $this->assertSame(1, $report['summary']['placeholder_products']);
        $this->assertSame(0, $report['summary']['projection_cue_products']);
        $this->assertSame(0, $report['summary']['longevity_cue_products']);
        $this->assertSame(['vanilla'], $report['products'][0]['normalized_layers']['top']['notes']);
        $this->assertArrayNotHasKey('description', $report['products'][0]);
        $this->assertStringContainsString("'=FAKE()", file_get_contents($this->directory.'/product-index.csv'));
        $this->assertStringContainsString("'+Test Brand", file_get_contents($this->directory.'/product-index.csv'));
    }

    public function test_snapshot_tampering_fails_before_outputs_are_created(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['products'][0]['notes']['base'] = ['Oud'];
        $this->assertSame(1, $this->runAudit($snapshot));
        $this->assertFileDoesNotExist($this->directory.'/catalog-audit.json');
        $this->assertFileDoesNotExist($this->directory.'/product-index.csv');
    }

    public function test_existing_outputs_are_not_overwritten_and_partial_outputs_are_cleaned(): void
    {
        file_put_contents($this->directory.'/product-index.csv', 'keep-existing');
        $this->assertSame(1, $this->runAudit($this->snapshot()));
        $this->assertSame('keep-existing', file_get_contents($this->directory.'/product-index.csv'));
        $this->assertFileDoesNotExist($this->directory.'/catalog-audit.json');
    }

    public function test_scenario_reference_has_thirty_pending_cases_and_five_held_out_profiles(): void
    {
        $document = json_decode(file_get_contents(__DIR__.'/../../docs/planning/fragrance-preference/scenarios.json'), true);
        $cases = $document['cases'];
        $this->assertCount(30, $cases);
        $this->assertCount(30, array_unique(array_column($cases, 'id')));
        $this->assertCount(20, array_filter($cases, fn ($case) => $case['kind'] === 'normal'));
        $this->assertCount(10, array_filter($cases, fn ($case) => $case['kind'] === 'edge'));
        $this->assertCount(5, array_filter($cases, fn ($case) => $case['held_out']));
        foreach ($cases as $case) {
            $this->assertSame('pending', $case['owner_review']['status']);
        }
    }
}
