<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Services\SpreadsheetSafeCell;
use App\Support\FragranceNoteNormalizer;

// Offline audit: this script does not bootstrap Laravel or connect to a database.
$options = getopt('', ['snapshot:', 'output:']);
$input = $options['snapshot'] ?? '';
$output = $options['output'] ?? '';
if (! is_file($input) || filesize($input) > 16 * 1024 * 1024 || ! is_dir($output)) {
    fwrite(STDERR, "Provide a snapshot <=16MiB and an existing output directory.\n");
    exit(1);
}
$files = [];
try {
    $snapshot = json_decode(file_get_contents($input), true, 64, JSON_THROW_ON_ERROR);
    if (($snapshot['schema'] ?? '') !== 'qammaris-public-fragrance-audit-v1' || ! is_array($snapshot['products'] ?? null) || count($snapshot['products']) > 5000) {
        throw new RuntimeException('Invalid snapshot.');
    }
    $catalogHash = hash('sha256', json_encode($snapshot['products'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    if (! hash_equals($catalogHash, $snapshot['catalog_sha256'] ?? '')) {
        throw new RuntimeException('Snapshot integrity mismatch.');
    }
    $dictionary = json_decode(file_get_contents(dirname(__DIR__, 2).'/resources/data/fragrance-note-aliases.json'), true, 64, JSON_THROW_ON_ERROR);
    $normalizer = new FragranceNoteNormalizer($dictionary);
    $summary = ['products' => 0, 'descriptions_present' => 0, 'all_note_layers_filled' => 0, 'placeholder_products' => 0,
        'note_entries' => 0, 'recognized_entries' => 0, 'partial_entries' => 0, 'unmapped_entries' => 0,
        'offer_issues' => 0, 'projection_cue_products' => 0, 'longevity_cue_products' => 0, 'availability' => [], 'gender' => []];
    $rows = [];
    $vocabulary = [];
    $unmapped = [];
    $identicalNotes = [];
    $ids = [];
    foreach ($snapshot['products'] as $product) {
        if (! is_int($product['id'] ?? null) || isset($ids[$product['id']]) || ! is_string($product['name'] ?? null) || ! is_string($product['slug'] ?? null)) {
            throw new RuntimeException('Invalid or duplicate public product row.');
        }
        $ids[$product['id']] = true;
        $summary['products']++;
        $description = trim(html_entity_decode(strip_tags($product['description'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $layers = $normalizer->layers($product['notes'] ?? null);
        $placeholder = false;
        $issues = [];
        $summary['descriptions_present'] += (int) ($description !== '');
        $allFilled = ! array_filter($layers, fn ($layer) => ! $layer['valid'] || ! $layer['entries']);
        $summary['all_note_layers_filled'] += (int) $allFilled;
        if (! $allFilled) {
            $issues[] = 'missing_or_malformed_notes';
        }
        foreach ($layers as $layer => $data) {
            foreach ($data['entries'] as $entry) {
                $summary['note_entries']++;
                $vocabulary[mb_strtolower(trim($entry['raw']))] = ($vocabulary[mb_strtolower(trim($entry['raw']))] ?? 0) + 1;
                $placeholder = $placeholder || $entry['placeholder'];
                if (isset($summary[$entry['status'].'_entries'])) {
                    $summary[$entry['status'].'_entries']++;
                }
                if ($entry['unmapped'] !== '') {
                    $issues[] = 'unmapped_note';
                    $unmapped[$entry['unmapped']][] = ['product_id' => $product['id'], 'layer' => $layer, 'raw' => $entry['raw']];
                }
            }
        }
        $summary['placeholder_products'] += (int) $placeholder;
        if ($placeholder) {
            $issues[] = 'placeholder_notes';
        }
        if (($product['active_offer_count'] ?? 0) !== 1 || ! is_numeric($product['price'] ?? null) || $product['price'] <= 0 || ! is_int($product['size_ml'] ?? null) || $product['size_ml'] <= 0) {
            $summary['offer_issues']++;
            $issues[] = 'invalid_single_offer';
        }
        $projectionCues = preg_match('/projection|proyeksi|sillage|sebar|kekuatan aroma|intensity|intensitas/iu', $description) === 1;
        $longevityCues = preg_match('/longevity|ketahanan|tahan lama|\d+\s*(?:[-–]\s*\d+\s*)?(?:jam|hours?)/iu', $description) === 1;
        $summary['projection_cue_products'] += (int) $projectionCues;
        $summary['longevity_cue_products'] += (int) $longevityCues;
        $status = $product['availability'] ?? 'unknown';
        $summary['availability'][$status] = ($summary['availability'][$status] ?? 0) + 1;
        $gender = $product['gender'] ?? 'unknown';
        $summary['gender'][$gender] = ($summary['gender'][$gender] ?? 0) + 1;
        $fingerprint = hash('sha256', json_encode(['description' => $product['description'] ?? null, 'notes' => $product['notes'] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $noteHash = hash('sha256', json_encode($product['notes'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if ($allFilled) {
            $identicalNotes[$noteHash][] = $product['id'];
        }
        $rows[] = ['id' => $product['id'], 'name' => $product['name'], 'slug' => $product['slug'], 'brand' => $product['brand'],
            'price' => $product['price'], 'size_ml' => $product['size_ml'], 'availability' => $status, 'gender' => $gender,
            'source_sha256' => $fingerprint, 'description_sha256' => hash('sha256', $product['description'] ?? ''),
            'description_words' => count(preg_split('/\s+/u', $description, -1, PREG_SPLIT_NO_EMPTY)),
            'projection_cue_only' => $projectionCues, 'longevity_cue_only' => $longevityCues,
            'normalized_layers' => $layers, 'review_flags' => array_values(array_unique($issues))];
    }
    usort($rows, fn ($a, $b) => $a['id'] <=> $b['id']);
    ksort($vocabulary);
    ksort($unmapped);
    $equalGroups = array_values(array_filter($identicalNotes, fn ($members) => count($members) > 1));
    foreach ($rows as &$row) {
        foreach ($equalGroups as $group) {
            if (in_array($row['id'], $group, true)) {
                $row['review_flags'][] = 'identical_notes_not_verified_identity';
            }
        }
    }
    unset($row);
    $report = ['schema' => 'qammaris-fragrance-audit-report-v1', 'captured_at' => $snapshot['captured_at'], 'source_label' => $snapshot['label'],
        'source_revision' => $snapshot['revision'], 'snapshot_file_sha256' => hash_file('sha256', $input),
        'catalog_sha256' => $snapshot['catalog_sha256'], 'legacy_input_sha256' => $snapshot['legacy_input_sha256'],
        'dictionary_version' => $dictionary['version'], 'dictionary_sha256' => hash_file('sha256', dirname(__DIR__, 2).'/resources/data/fragrance-note-aliases.json'),
        'summary' => $summary, 'raw_note_vocabulary' => $vocabulary, 'unmapped_terms' => $unmapped,
        'identical_note_groups_unverified' => $equalGroups, 'products' => $rows, 'legacy_baselines' => $snapshot['legacy_baselines'],
        'limits' => ['Notes are exact search groups, not performance facts or interchangeable ingredients.', 'Description cue counts are not validated attribute values.', 'Identical notes are not proof of the same fragrance.', 'No Owner reviews, calibration, new ranking or public UI are implemented.']];
    $path = $output.'/catalog-audit.json';
    $handle = fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('Output already exists or cannot be created.');
    }
    $files[] = $path;
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    if (fwrite($handle, $json) !== strlen($json)) {
        fclose($handle);
        throw new RuntimeException('Output write failed.');
    }
    fclose($handle);
    $path = $output.'/product-index.csv';
    $handle = fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('CSV output already exists or cannot be created.');
    }
    $files[] = $path;
    $safe = new SpreadsheetSafeCell;
    if (fwrite($handle, "\xEF\xBB\xBF") !== 3) {
        fclose($handle);
        throw new RuntimeException('CSV header write failed.');
    }
    if (fputcsv($handle, ['id', 'name', 'brand', 'price', 'size_ml', 'availability', 'review_flags', 'url'], escape: '') === false) {
        fclose($handle);
        throw new RuntimeException('CSV header write failed.');
    }
    foreach ($rows as $row) {
        $cells = [$row['id'], $row['name'], $row['brand'], $row['price'], $row['size_ml'], $row['availability'], implode('|', $row['review_flags']), 'https://qammarisparfum.id/products/'.rawurlencode($row['slug'])];
        if (fputcsv($handle, array_map($safe->sanitize(...), $cells), escape: '') === false) {
            fclose($handle);
            throw new RuntimeException('CSV write failed.');
        }
    }
    fclose($handle);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $error) {
    foreach ($files as $file) {
        unlink($file); // Only outputs newly created by this invocation; never input or existing files.
    }
    fwrite(STDERR, 'Offline audit failed: '.$error::class."\n");
    exit(1);
}
