<?php

namespace Tests\Unit;

use App\Support\FragrancePreference\ProfileBuilder;
use App\Support\FragrancePreference\RecommendationEngine;
use PHPUnit\Framework\TestCase;

class FragrancePreferenceEvaluationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/qammaris-preference-eval-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (['source.json', 'report.json'] as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }
        rmdir($this->directory);
    }

    private function snapshot(): array
    {
        $product = ['id' => 1, 'name' => 'Synthetic', 'description' => "Projection: Strong\nLongevity: 8–10 hours\nKetahanan Aroma: 10–12 jam", 'notes' => ['top' => ['Bergamot'], 'middle' => ['Jasmine'], 'base' => ['Musk']], 'price' => '200000.00', 'size_ml' => 50, 'active_offer_count' => 1, 'availability' => 'sold_out', 'gender' => 'Unisex'];
        $cases = json_decode(file_get_contents(__DIR__.'/../../docs/planning/fragrance-preference/scenarios.json'), true)['cases'];
        $legacy = [];
        foreach ($cases as $case) {
            if ($case['kind'] === 'normal') {
                $legacy[] = ['case_id' => $case['id'], 'legacy_answers' => $case['legacy_answers'], 'product_ids' => [1]];
            }
        }

        return ['schema' => 'qammaris-public-fragrance-audit-v1', 'products' => [$product], 'catalog_sha256' => ProfileBuilder::hash([$product]), 'captured_at' => '2026-10-10T00:00:00Z', 'revision' => null, 'legacy_baselines' => $legacy];
    }

    private function runEvaluation(array $snapshot, array $options = []): int
    {
        file_put_contents($this->directory.'/source.json', json_encode($snapshot, JSON_UNESCAPED_UNICODE));
        $process = proc_open([PHP_BINARY, __DIR__.'/../../tools/fragrance-quiz/evaluate.php', '--snapshot='.$this->directory.'/source.json', '--output='.$this->directory.'/report.json', ...$options], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }

    public function test_training_excludes_holdout_and_does_not_claim_human_accuracy(): void
    {
        $snapshot = $this->snapshot();
        $this->assertSame(0, $this->runEvaluation($snapshot));
        $report = json_decode(file_get_contents($this->directory.'/report.json'), true);
        $this->assertCount(15, $report['normal_results']);
        $this->assertSame(0, $report['summary']['human_labels_available']);
        $this->assertFalse($report['includes_holdout']);
        $this->assertNull($report['profiles'][0]['attributes']['longevity']);
        $this->assertStringContainsString('not_established', $report['quality_acceptance']);
        $this->assertSame($snapshot, json_decode(file_get_contents($this->directory.'/source.json'), true));
    }

    public function test_holdout_requires_exact_frozen_engine_and_includes_twenty_only_after_freeze(): void
    {
        $this->assertSame(1, $this->runEvaluation($this->snapshot(), ['--include-holdout', '--frozen-engine='.str_repeat('0', 64)]));
        $this->assertFileDoesNotExist($this->directory.'/report.json');
        $answers = ['budget_max' => null, 'likes' => [], 'avoid' => [], 'use' => 'any', 'environment' => 'any', 'sweetness' => 'any', 'projection' => 'unknown', 'longevity' => 'not_priority'];
        $fingerprint = (new RecommendationEngine(ProfileBuilder::fromFiles()))->recommend($answers, [])['engine_fingerprint'];
        $this->assertSame(0, $this->runEvaluation($this->snapshot(), ['--include-holdout', '--frozen-engine='.$fingerprint]));
        $report = json_decode(file_get_contents($this->directory.'/report.json'), true);
        $this->assertCount(20, $report['normal_results']);
        $this->assertSame($fingerprint, $report['engine_fingerprint']);
    }

    public function test_tampered_snapshot_and_existing_output_are_rejected_without_overwrite(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['products'][0]['notes']['base'][] = 'Oud';
        $this->assertSame(1, $this->runEvaluation($snapshot));
        $this->assertFileDoesNotExist($this->directory.'/report.json');
        file_put_contents($this->directory.'/report.json', 'keep-existing');
        $this->assertSame(1, $this->runEvaluation($this->snapshot()));
        $this->assertSame('keep-existing', file_get_contents($this->directory.'/report.json'));
    }
}
