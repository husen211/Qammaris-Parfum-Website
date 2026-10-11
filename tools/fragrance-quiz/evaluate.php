<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Support\FragrancePreference\ProfileBuilder;
use App\Support\FragrancePreference\RecommendationEngine;
use App\Support\Rupiah;

// Offline only: no Laravel bootstrap, database, network or catalog write.
$options = getopt('', ['snapshot:', 'output:', 'include-holdout', 'frozen-engine:']);
$input = $options['snapshot'] ?? '';
$output = $options['output'] ?? '';
$handle = null;
$created = false;
try {
    if (! is_file($input) || filesize($input) > 16 * 1024 * 1024 || ! is_dir(dirname($output)) || $output === '' || is_file($output)) {
        throw new RuntimeException('Provide bounded input and a new output file.');
    }
    $source = json_decode(file_get_contents($input), true, 64, JSON_THROW_ON_ERROR);
    if (($source['schema'] ?? '') !== 'qammaris-public-fragrance-audit-v1' || ! is_array($source['products'] ?? null) || count($source['products']) > 5000 || ! hash_equals(ProfileBuilder::hash($source['products']), $source['catalog_sha256'] ?? '')) {
        throw new RuntimeException('Invalid snapshot/integrity.');
    }
    $builder = ProfileBuilder::fromFiles();
    $engine = new RecommendationEngine($builder);
    $blank = ['budget_max' => null, 'likes' => [], 'avoid' => [], 'use' => 'any', 'environment' => 'any', 'sweetness' => 'any', 'projection' => 'unknown', 'longevity' => 'not_priority'];
    $engineFingerprint = $engine->recommend($blank, [])['engine_fingerprint'];
    if (isset($options['include-holdout']) && ! hash_equals($engineFingerprint, $options['frozen-engine'] ?? '')) {
        throw new RuntimeException('Hold-out requires the frozen training engine fingerprint.');
    }
    $catalog = $profiles = [];
    $ids = [];
    foreach ($source['products'] as $product) {
        if (! is_int($product['id'] ?? null) || isset($ids[$product['id']]) || ! is_string($product['name'] ?? null)) {
            throw new RuntimeException('Invalid or duplicate product row.');
        }
        $ids[$product['id']] = true;
        $profile = $builder->build($product);
        $profiles[$product['id']] = $profile;
        // The enforced read-only export schema contains only public products at capture time.
        $catalog[] = [...$product, 'public' => true, 'hidden' => false, 'archived' => false, 'profile' => $profile];
    }
    $casesFile = dirname(__DIR__, 2).'/docs/planning/fragrance-preference/scenarios.json';
    $cases = json_decode(file_get_contents($casesFile), true, 64, JSON_THROW_ON_ERROR)['cases'];
    $legacy = array_column($source['legacy_baselines'], null, 'case_id');
    $results = [];
    $summary = ['normal_cases_evaluated' => 0, 'empty_results' => 0, 'agent_proposal_overlap_at_least_one' => 0, 'agent_proposal_overlap_at_least_two' => 0, 'legacy_proposal_overlap_at_least_one' => 0, 'legacy_proposal_overlap_at_least_two' => 0, 'legacy_above_budget_choices' => 0, 'legacy_detected_avoid_choices' => 0, 'human_labels_available' => 0];
    foreach ($cases as $case) {
        if ($case['kind'] !== 'normal' || ($case['held_out'] && ! isset($options['include-holdout']))) {
            continue;
        }
        if (($legacy[$case['id']]['legacy_answers'] ?? null) !== $case['legacy_answers']) {
            throw new RuntimeException('Legacy mapping changed; capture a matching baseline.');
        }
        $result = $engine->recommend($case['answers'], $catalog);
        $chosen = array_column($result['main'], 'product_id');
        $alternative = array_column($result['alternative'], 'product_id');
        $reference = $case['agent_reference']['primary_candidate_ids'] ?? [];
        $oldIds = $legacy[$case['id']]['product_ids'];
        $oldViolations = [];
        foreach ($oldIds as $id) {
            $row = array_values(array_filter($catalog, fn ($p) => $p['id'] === $id))[0] ?? null;
            if (! $row) {
                throw new RuntimeException('Non-public legacy ID.');
            }
            if ($case['answers']['budget_max'] !== null && Rupiah::minorUnits($row['price']) > $case['answers']['budget_max'] * 100) {
                $oldViolations[] = ['product_id' => $id, 'rule' => 'above_budget_not_separate_alternative'];
                $summary['legacy_above_budget_choices']++;
            }
            if (array_intersect($case['answers']['avoid'], $profiles[$id]['attributes']['families'])) {
                $oldViolations[] = ['product_id' => $id, 'rule' => 'detected_avoid'];
                $summary['legacy_detected_avoid_choices']++;
            }
        }
        $agreement = count(array_intersect($chosen, $reference));
        $oldAgreement = count(array_intersect($oldIds, $reference));
        $summary['normal_cases_evaluated']++;
        $summary['empty_results'] += (int) $result['empty'];
        $summary['agent_proposal_overlap_at_least_one'] += (int) ($agreement >= 1);
        $summary['agent_proposal_overlap_at_least_two'] += (int) ($agreement >= 2);
        $summary['legacy_proposal_overlap_at_least_one'] += (int) ($oldAgreement >= 1);
        $summary['legacy_proposal_overlap_at_least_two'] += (int) ($oldAgreement >= 2);
        $review = $case['owner_review'];
        $human = ($review['status'] ?? '') === 'approved' && ! empty($review['reviewer']) && ! empty($review['reviewed_at']) && is_array($review['relevant_product_ids'] ?? null) ? $review : null;
        $summary['human_labels_available'] += (int) ($human !== null);
        $results[] = ['case_id' => $case['id'], 'held_out' => $case['held_out'], 'answers' => $case['answers'], 'main_ids' => $chosen, 'alternative_ids' => $alternative, 'result' => $result, 'legacy_ids' => $oldIds, 'legacy_rule_violations' => $oldViolations, 'agent_reference_ids' => $reference, 'agent_proposal_overlap' => $agreement, 'legacy_proposal_overlap' => $oldAgreement, 'human_relevant_overlap' => $human ? count(array_intersect($chosen, $human['relevant_product_ids'])) : null];
    }
    $coverage = array_fill_keys(['families', 'sweetness', 'projection', 'longevity', 'context'], 0);
    $issueProducts = [];
    foreach ($profiles as $id => $profile) {
        foreach ($coverage as $key => $unused) {
            $coverage[$key] += (int) ! empty($profile['attributes'][$key]);
        }
        if ($profile['review_issues']) {
            $issueProducts[] = ['product_id' => $id, 'issues' => $profile['review_issues'], 'unknowns' => $profile['unknowns']];
        }
    }
    $report = ['schema' => 'qammaris-preference-evaluation-v1', 'engine_version' => RecommendationEngine::VERSION, 'engine_fingerprint' => $engineFingerprint, 'parser_fingerprint' => $builder->parserFingerprint(), 'source_capture' => ['captured_at' => $source['captured_at'], 'revision' => $source['revision'], 'catalog_sha256' => $source['catalog_sha256']], 'scenario_sha256' => hash_file('sha256', $casesFile), 'includes_holdout' => isset($options['include-holdout']), 'summary' => $summary, 'quality_acceptance' => 'not_established_requires_independent_human_labels_and_all_edge_checks', 'limits' => ['Overlap with agent proposals is a source-derived self-comparison, not accuracy or independent relevance.', 'Legacy cannot express these answers exactly; mapped baseline only.', 'Prices/status are dated snapshot values; live adapter rechecks the catalog.', 'No weights tuned using hold-out; engine is provisional, not release accepted.'], 'profile_coverage' => $coverage, 'review_products' => $issueProducts, 'profiles' => array_values($profiles), 'normal_results' => $results];
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    $handle = fopen($output, 'x');
    if ($handle === false) {
        throw new RuntimeException('Cannot create output.');
    }
    $created = true;
    if (fwrite($handle, $json) !== strlen($json)) {
        throw new RuntimeException('Output write failed.');
    }
    fclose($handle);
    $handle = null;
    echo json_encode(['engine_fingerprint' => $engineFingerprint, 'summary' => $summary, 'profile_coverage' => $coverage, 'quality_acceptance' => $report['quality_acceptance']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    if ($created) {
        unlink($output); // Only this invocation's new report, never input/existing outputs.
    }
    fwrite(STDERR, 'Offline evaluation failed: '.$error::class."\n");
    exit(1);
}
