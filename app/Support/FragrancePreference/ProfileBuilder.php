<?php

namespace App\Support\FragrancePreference;

use App\Support\FragranceNoteNormalizer;
use InvalidArgumentException;

final class ProfileBuilder
{
    private FragranceNoteNormalizer $normalizer;

    private ?string $fingerprint = null;

    public function __construct(private readonly array $dictionary, private readonly array $rules)
    {
        $this->normalizer = new FragranceNoteNormalizer($dictionary);
        if (($rules['schema'] ?? '') !== 'qammaris-fragrance-profile-rules-v1' || array_keys($rules['labels']) !== PreferenceAnswers::FAMILIES) {
            throw new InvalidArgumentException('Invalid profile rules.');
        }
        foreach ($rules['note_families'] as $family => $notes) {
            if (! in_array($family, PreferenceAnswers::FAMILIES, true) || array_diff($notes, array_keys($dictionary['notes']))) {
                throw new InvalidArgumentException('Unknown note family mapping.');
            }
        }
    }

    public static function fromFiles(): self
    {
        $root = dirname(__DIR__, 3);

        return new self(json_decode(file_get_contents($root.'/resources/data/fragrance-note-aliases.json'), true, 64, JSON_THROW_ON_ERROR), json_decode(file_get_contents($root.'/resources/data/fragrance-profile-rules.json'), true, 64, JSON_THROW_ON_ERROR));
    }

    public function parserFingerprint(): string
    {
        return $this->fingerprint ??= self::hash([$this->dictionary, $this->rules, hash_file('sha256', __FILE__), hash_file('sha256', dirname(__DIR__).'/FragranceNoteNormalizer.php'), hash_file('sha256', __DIR__.'/PreferenceAnswers.php')]);
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function sourceFingerprint(array $source): string
    {
        return self::hash(['description' => $source['description'] ?? null, 'notes' => $source['notes'] ?? null]);
    }

    public function build(array $source): array
    {
        if (! is_int($source['id'] ?? null) || $source['id'] < 1 || ! is_string($source['description'] ?? '') || strlen($source['description'] ?? '') > 200000 || ! mb_check_encoding($source['description'] ?? '', 'UTF-8')) {
            throw new InvalidArgumentException('Invalid profile source.');
        }
        $layers = $this->normalizer->layers($source['notes'] ?? null);
        $families = $evidence = $issues = [];
        foreach ($layers as $layer => $data) {
            if (! $data['valid'] || ! $data['entries']) {
                $issues[] = ['code' => 'missing_notes', 'layer' => $layer];
            }
            foreach ($data['entries'] as $entry) {
                if ($entry['placeholder'] || $entry['unmapped'] !== '') {
                    $issues[] = ['code' => $entry['placeholder'] ? 'placeholder' : 'unknown_note', 'layer' => $layer, 'raw' => $entry['raw']];
                }
                foreach ($entry['notes'] as $note) {
                    $mapped = false;
                    foreach ($this->rules['note_families'] as $family => $notes) {
                        if (in_array($note, $notes, true)) {
                            $mapped = true;
                            $families[$family] = true;
                            $evidence['families.'.$family][] = ['source' => 'notes', 'layer' => $layer, 'raw' => $entry['raw'], 'canonical' => $note, 'certainty' => 'inferred_family'];
                        }
                    }
                    if (! $mapped) {
                        $issues[] = ['code' => 'unassigned_note_family', 'layer' => $layer, 'raw' => $entry['raw'], 'canonical' => $note];
                    }
                }
            }
        }
        // Preserve labelled lines; narrative/promotion is not a performance measurement.
        $text = html_entity_decode(strip_tags(preg_replace('/<\s*(?:br\s*\/?|\/p|\/div|\/li)\s*>/iu', "\n", $source['description'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $claims = ['sweetness' => [], 'projection' => [], 'longevity' => [], 'context' => []];
        $descriptionFamilies = [];
        foreach (preg_split('/[\r\n.!?]+/u', $text) as $statement) {
            $normalizedStatement = FragranceNoteNormalizer::text($statement);
            if (preg_match('/\b(?:tidak|bukan|tanpa|not|no|without|belum|mungkin)\b/u', $normalizedStatement)) {
                continue;
            }
            // Only an explicit character/predicate, not arbitrary perfume story words.
            if (! preg_match('/\b(?:karakter(?: aroma)?|profil aroma|aroma utamanya|menghadirkan perpaduan|menawarkan kombinasi)\s+(.{1,180})/u', $normalizedStatement, $m)
                && ! preg_match('/\b(?:parfum|fragrance|perfume)\s+(gourmand|floral|woody|aquatic|musky|powdery|fruity)(?:\s|$)(.{0,100})/u', $normalizedStatement, $m)) {
                continue;
            }
            $phrase = implode(' ', array_slice($m, 1));
            foreach ($this->rules['description_family_terms'] as $family => $terms) {
                if ($this->hasTerm($phrase, $terms)) {
                    $descriptionFamilies[$family] = true;
                    $families[$family] = true;
                    $evidence['families.'.$family][] = ['source' => 'description_fact', 'raw' => trim($statement), 'certainty' => 'catalog_character_claim'];
                }
            }
        }
        foreach (preg_split('/[\r\n]+/u', $text) as $line) {
            $line = preg_replace('/^[\s*•-]+|[\s*•-]+$/u', '', $line);
            if (! preg_match('/^(?:[\p{So}\p{Sk}]\s*)*([\p{L}\s\/]+)\s*:\s*(.+)$/u', $line, $match)) {
                // A literal use recommendation is factual context, not an intensity claim.
                foreach (preg_split('/[.!?]+/u', $line) as $sentence) {
                    $normalizedSentence = FragranceNoteNormalizer::text($sentence);
                    if (preg_match('/\bcocok (?:digunakan |dipakai |digunakan untuk |untuk )/u', $normalizedSentence)
                        && ! preg_match('/\b(?:tidak|bukan|tanpa|kurang|not|no|without|less|belum|mungkin)\b/u', $normalizedSentence)) {
                        foreach ($this->factValues('context', $normalizedSentence, $sentence) as $claim) {
                            $claims['context'][self::hash($claim)] = $claim;
                            $evidence['context'][] = ['source' => 'description_use_statement', 'raw' => trim($sentence), 'value' => $claim, 'certainty' => 'catalog_claim_not_measured'];
                        }
                    }
                }

                continue;
            }
            $label = FragranceNoteNormalizer::text($match[1]);
            $value = trim($match[2]);
            $normalized = FragranceNoteNormalizer::text($value);
            $attribute = match ($label) {
                'profile aroma', 'profil aroma', 'karakter aroma', 'aroma', 'scent profile', 'main accords' => 'families',
                'kemanisan', 'sweetness', 'tingkat kemanisan' => 'sweetness',
                'kekuatan aroma', 'projection', 'proyeksi', 'sillage', 'intensitas', 'intensitas aroma' => 'projection',
                'ketahanan', 'ketahanan aroma', 'daya tahan', 'daya tahan aroma', 'longevity' => 'longevity',
                'penggunaan', 'pemakaian', 'usage', 'cocok digunakan', 'cocok untuk', 'occasions', 'waktu', 'waktu pemakaian', 'waktu pemakian' => 'context',
                default => null,
            };
            if ($attribute === null) {
                continue;
            }
            if (preg_match('/\b(?:tidak|bukan|tanpa|kurang|not|no|without|less|belum|maybe|mungkin)\b/u', $normalized)) {
                $issues[] = ['code' => 'negated_or_uncertain_claim', 'attribute' => $attribute, 'raw' => $line];

                continue;
            }
            if ($attribute === 'families') {
                foreach ($this->rules['description_family_terms'] as $family => $terms) {
                    if ($this->hasTerm($normalized, $terms)) {
                        $descriptionFamilies[$family] = true;
                        $families[$family] = true;
                        $evidence['families.'.$family][] = ['source' => 'description_fact', 'raw' => $line, 'certainty' => 'catalog_claim'];
                    }
                }

                continue;
            }
            $values = $this->factValues($attribute, $normalized, $value);
            if (! $values) {
                $issues[] = ['code' => 'unparsed_fact', 'attribute' => $attribute, 'raw' => $line];
            }
            foreach ($values as $claim) {
                $claims[$attribute][self::hash($claim)] = $claim;
                $evidence[$attribute][] = ['source' => 'description_fact', 'raw' => $line, 'value' => $claim, 'certainty' => 'catalog_claim_not_measured'];
            }
        }
        $attributes = ['families' => array_keys($families), 'aroma_target' => array_keys($descriptionFamilies ?: $families), 'sweetness' => null, 'projection' => null, 'longevity' => null, 'context' => []];
        foreach ($claims as $attribute => $values) {
            if ($attribute === 'context') {
                $attributes[$attribute] = array_values($values);
            } elseif (count($values) === 1) {
                $attributes[$attribute] = array_values($values)[0];
            } elseif (count($values) > 1) {
                $issues[] = ['code' => 'conflicting_fact', 'attribute' => $attribute, 'values' => array_values($values)];
            }
        }
        foreach ($this->rules['source_review_cases'] ?? [] as $case) {
            if ($case['product_id'] !== $source['id']) {
                continue;
            }
            $current = $case['source_fingerprint'] === self::sourceFingerprint($source);
            $issues[] = ['code' => $current ? 'source_conflict_requires_review' : 'previous_source_conflict_recheck', 'issue' => $case['issue'], 'evidence_excerpts' => $case['evidence_excerpts']];
            if ($current) {
                foreach ($case['attributes_to_suspend'] as $attribute) {
                    $attributes[$attribute] = null;
                }
            }
        }
        foreach (['families', 'aroma_target', 'context'] as $attribute) {
            sort($attributes[$attribute]);
        }

        return ['product_id' => $source['id'], 'parser_version' => $this->rules['version'], 'parser_fingerprint' => $this->parserFingerprint(), 'source_fingerprint' => self::sourceFingerprint($source), 'attributes' => $attributes, 'evidence' => $evidence, 'review_issues' => $issues, 'unknowns' => $this->unknowns($attributes), 'normalized_layers' => $layers];
    }

    public function unknowns(array $attributes): array
    {
        return array_values(array_filter(['families', 'sweetness', 'projection', 'longevity', 'context'], fn ($key) => empty($attributes[$key])));
    }

    private function hasTerm(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/u', $text)) {
                return true;
            }
        }

        return false;
    }

    private function factValues(string $attribute, string $text, string $raw): array
    {
        if ($attribute === 'longevity') {
            if (preg_match('/(?<![\d.])(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?\s*\+?\s*(?:jam|hours?)(?!\w)/iu', $raw, $m)) {
                $min = (int) $m[1];
                $max = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $min;

                return $min >= 1 && $max >= $min && $max <= 48 ? [['min_hours' => $min, 'max_hours' => str_contains($raw, '+') ? null : $max]] : [];
            }

            return [];
        }
        $map = match ($attribute) {
            'sweetness' => ['light' => ['ringan', 'low', 'light', 'sedikit manis'], 'medium' => ['sedang', 'moderate', 'medium'], 'sweet' => ['tinggi', 'high', 'sweet', 'manis']],
            'projection' => ['close' => ['dekat badan', 'intimate', 'soft', 'lemah', 'skin scent'], 'medium' => ['sedang', 'moderate', 'medium'], 'strong' => ['kuat', 'strong', 'powerful']],
            'context' => ['daily' => ['harian', 'daily', 'sehari hari'], 'office' => ['kantor', 'kuliah', 'office', 'kerja', 'bekerja'], 'casual' => ['santai', 'casual'], 'event' => ['acara', 'date', 'malam', 'evening', 'formal'], 'ac' => ['indoor', 'ac', 'ruangan ber ac'], 'outdoor' => ['outdoor', 'luar ruangan'], 'mixed' => ['campuran', 'mixed', 'indoor outdoor']],
            default => [],
        };
        $values = [];
        // Compound "sedikit manis" is a single low claim, not two conflicting claims.
        if ($attribute === 'sweetness' && str_contains($text, 'sedikit manis')) {
            return ['light'];
        }
        foreach ($map as $value => $terms) {
            if ($this->hasTerm($text, $terms)) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
