<?php

namespace App\Support\FragrancePreference;

use App\Support\Rupiah;
use InvalidArgumentException;

final class RecommendationEngine
{
    public const VERSION = 'pref-02.1-provisional';

    private const WEIGHTS = ['aroma' => 50, 'sweetness' => 15, 'projection' => 10, 'longevity' => 10, 'context' => 10, 'gender' => 5];

    public function __construct(private readonly ProfileBuilder $builder) {}

    /** Catalog rows are current public metadata; profiles must match their source and parser. */
    public function recommend(array $input, array $catalog, bool $readyOnly = false): array
    {
        $answers = PreferenceAnswers::validate($input);
        if (count($catalog) > 5000) {
            throw new InvalidArgumentException('Catalog exceeds recommendation bound.');
        }
        $eligible = [];
        $seen = [];
        foreach ($catalog as $row) {
            if (! is_int($row['id'] ?? null) || isset($seen[$row['id']])) {
                throw new InvalidArgumentException('Duplicate or invalid product ID.');
            }
            $seen[$row['id']] = true;
            if ($this->eligible($row)) {
                $eligible[$row['id']] = $row;
            }
        }
        $favorite = $eligible[$answers['favorite_product_id']] ?? null;
        $favoriteFamilies = $favorite['profile']['attributes']['aroma_target'] ?? [];
        $exploration = ! $answers['likes'] && ! $favoriteFamilies;
        $ranked = [];
        foreach ($eligible as $row) {
            $attributes = $row['profile']['attributes'];
            if ($readyOnly && ($row['availability'] ?? '') !== 'available') {
                continue;
            }
            if (array_intersect($answers['avoid'], $attributes['families'])) {
                continue;
            }
            $components = $this->score($answers, $row, $favoriteFamilies);
            // No padding: personal choices need aroma support; exploration needs answered context support.
            if ((! $exploration && $components['aroma'] === 0) || ($exploration && $components['context'] === 0)) {
                continue;
            }
            $ranked[] = $this->result($row, $answers, $components, $exploration, $favoriteFamilies);
        }
        usort($ranked, $this->compare(...));
        $budgetMinor = $answers['budget_max'] === null ? null : $answers['budget_max'] * 100;
        $main = $above = [];
        $identities = [];
        foreach ($ranked as $row) {
            $identity = $row['identity_key'];
            if ($identity !== null && isset($identities[$identity])) {
                continue;
            }
            $price = $row['price_minor'];
            if ($budgetMinor === null || $price <= $budgetMinor) {
                if (count($main) < 3) {
                    $main[] = $row;
                    if ($identity !== null) {
                        $identities[$identity] = true;
                    }
                }
            } elseif ($price * 10 <= $budgetMinor * 11) {
                $above[] = $row;
            }
        }
        $alternative = [];
        foreach ($above as $row) {
            if ($row['identity_key'] !== null && isset($identities[$row['identity_key']])) {
                continue;
            }
            if (count($main) < 3 || $row['score_internal'] > $main[2]['score_internal']) {
                $row['budget_label'] = 'Alternatif di atas budget (maksimal 10%).';
                $alternative[] = $row;
                break;
            }
        }

        return ['engine_fingerprint' => ProfileBuilder::hash([self::VERSION, self::WEIGHTS, hash_file('sha256', __FILE__), $this->builder->parserFingerprint()]), 'engine_version' => self::VERSION, 'answers' => $answers, 'mode' => $exploration ? 'exploration' : 'personal', 'title' => $exploration ? 'Pilihan awal untuk dieksplorasi' : 'Pilihan berdasarkan preferensi', 'main' => $main, 'alternative' => $alternative, 'empty' => ! $main && ! $alternative, 'empty_actions' => ['Ubah budget', 'Ubah preferensi'], 'ready_only' => $readyOnly, 'limitations' => $answers['favorite_product_id'] !== null && ! $favorite ? ['Parfum favorit tidak tersedia sebagai profil publik terkini; tidak dipakai dalam penilaian.'] : []];
    }

    private function eligible(array $row): bool
    {
        $profile = $row['profile'] ?? null;
        if (($row['public'] ?? false) !== true || ($row['hidden'] ?? true) !== false || ($row['archived'] ?? true) !== false || ($row['active_offer_count'] ?? 0) !== 1 || ! is_int($row['size_ml'] ?? null) || $row['size_ml'] < 1 || ! is_array($profile)) {
            return false;
        }
        try {
            $price = Rupiah::minorUnits($row['price'] ?? '0');
        } catch (\Throwable) {
            return false;
        }
        if ($price < 1 || $price > 9999999999 || ($profile['product_id'] ?? null) !== $row['id'] || ($profile['source_fingerprint'] ?? '') !== ProfileBuilder::sourceFingerprint($row) || ($profile['parser_fingerprint'] ?? '') !== $this->builder->parserFingerprint()) {
            return false;
        }
        $attributes = $profile['attributes'] ?? [];
        if (! is_array($attributes['families'] ?? null) || ! $attributes['families'] || ! is_array($attributes['aroma_target'] ?? null) || ! $attributes['aroma_target']) {
            return false;
        }
        foreach ($attributes['families'] as $family) {
            if (! in_array($family, PreferenceAnswers::FAMILIES, true) || empty($profile['evidence']['families.'.$family])) {
                return false;
            }
        }

        return true;
    }

    private function score(array $answers, array $row, array $favorite): array
    {
        $a = $row['profile']['attributes'];
        $score = array_fill_keys(array_keys(self::WEIGHTS), 0);
        $likes = $answers['likes'];
        $target = $a['aroma_target'];
        $overlap = function (array $requested) use ($target, $row): float {
            if (! $requested) {
                return 0;
            }
            $support = 0;
            foreach (array_intersect($requested, $target) as $family) {
                $fact = array_filter($row['profile']['evidence']['families.'.$family], fn ($entry) => in_array($entry['source'], ['description_fact', 'review'], true));
                // Explicit catalog character statements outrank inferred note membership.
                $support += $fact ? 1 : 0.8;
            }

            return $support / count($requested);
        };
        // Fixed maxima, never divide by the number of known product attributes.
        $score['aroma'] = (int) floor(1000 * ($likes ? (($favorite ? 45 : 50) * $overlap($likes) + ($overlap($likes) > 0 ? 5 * $overlap($favorite) : 0)) : 50 * $overlap($favorite)));
        foreach (['sweetness', 'projection'] as $key) {
            if (! in_array($answers[$key], ['any', 'unknown'], true) && $a[$key] !== null && ! empty($row['profile']['evidence'][$key]) && $answers[$key] === $a[$key]) {
                $score[$key] = self::WEIGHTS[$key] * 1000;
            }
        }
        $minHours = $a['longevity']['min_hours'] ?? null;
        if ($minHours !== null && ! empty($row['profile']['evidence']['longevity']) && (($answers['longevity'] === 'few_hours' && $minHours >= 3) || ($answers['longevity'] === 'all_day' && $minHours >= 8))) {
            $score['longevity'] = self::WEIGHTS['longevity'] * 1000;
        }
        $contexts = array_values(array_filter([$answers['use'], $answers['environment']], fn ($v) => $v !== 'any'));
        if ($contexts && ! empty($row['profile']['evidence']['context'])) {
            $supported = count(array_filter($contexts, fn ($context) => in_array($context, $a['context'], true) || ($context === 'mixed' && in_array('ac', $a['context'], true) && in_array('outdoor', $a['context'], true))));
            $score['context'] = (int) floor(10000 * $supported / count($contexts));
        }
        if ($answers['gender'] !== 'all' && mb_strtolower($row['gender'] ?? '') === $answers['gender']) {
            $score['gender'] = 5000;
        }

        return $score;
    }

    private function result(array $row, array $answers, array $components, bool $exploration, array $favoriteFamilies): array
    {
        $profile = $row['profile'];
        $attributes = $profile['attributes'];
        $reasons = $limitations = [];
        if ($components['aroma'] > 0) {
            $matched = array_values(array_intersect($answers['likes'] ?: $favoriteFamilies, $attributes['aroma_target']));
            $reasons[] = ['code' => $answers['likes'] ? 'liked_aroma' : 'favorite_aroma', 'text' => 'Katalog mendukung arah aroma '.implode(', ', array_map($this->familyLabel(...), $matched)).'.', 'evidence_keys' => array_map(fn ($f) => 'families.'.$f, $matched)];
        }
        foreach (['sweetness' => 'Kemanisan yang diminta tercantum dalam data katalog.', 'projection' => 'Sebaran yang diminta tercantum dalam data katalog.', 'longevity' => 'Klaim ketahanan katalog mendukung kebutuhan waktu yang dipilih.', 'context' => 'Konteks pemakaian yang dipilih disebut dalam katalog.'] as $key => $text) {
            if ($components[$key] > 0) {
                $reasons[] = ['code' => $key, 'text' => $text, 'evidence_keys' => [$key]];
            }
            $requested = $key === 'context' ? ($answers['use'] !== 'any' || $answers['environment'] !== 'any') : ! in_array($answers[$key], ['any', 'unknown', 'not_priority'], true);
            if ($requested && empty($attributes[$key])) {
                $limitations[] = 'Data '.$this->attributeLabel($key).' belum diketahui.';
            } elseif (in_array($key, ['sweetness', 'projection', 'longevity'], true) && ! in_array($answers[$key], ['any', 'unknown', 'not_priority'], true) && $components[$key] === 0) {
                $limitations[] = 'Data '.$this->attributeLabel($key).' belum mendukung kebutuhan yang dipilih.';
            }
        }
        $price = Rupiah::minorUnits($row['price']);
        if (count($reasons) < 2) {
            $within = $answers['budget_max'] === null || $price <= $answers['budget_max'] * 100;
            $reasons[] = ['code' => 'budget', 'text' => $answers['budget_max'] === null ? 'Harga katalog '.Rupiah::format($row['price']).'; budget tidak dibatasi.' : ($within ? 'Harga sesuai budget yang dipilih.' : 'Harga berada dalam batas alternatif +10%.'), 'evidence_keys' => [], 'catalog_evidence' => ['price' => $row['price'], 'budget_max' => $answers['budget_max']]];
        }
        if ($profile['review_issues']) {
            $limitations[] = 'Ada data katalog yang belum lengkap atau memerlukan review; catatan ini tidak membuktikan bebas karakter/bahan tertentu.';
        }
        $limitations[] = 'Karakter dan performa berasal dari katalog, belum pengujian aroma langsung.';
        $identity = $profile['verified_identity'] ?? null;

        return ['product_id' => $row['id'], 'name' => $row['name'] ?? '', 'price' => $row['price'], 'price_minor' => $price, 'size_ml' => $row['size_ml'], 'availability' => $row['availability'] ?? 'unknown', 'score_internal' => array_sum($components), 'score_components_internal' => $components, 'evidence_completeness' => count(array_filter([$attributes['families'], $attributes['sweetness'], $attributes['projection'], $attributes['longevity'], $attributes['context']])), 'identity_key' => is_array($identity) && ($identity['status'] ?? '') === 'verified' && ! empty($identity['evidence']) ? $identity['key'] : null, 'source_fingerprint' => $profile['source_fingerprint'], 'profile_fingerprint' => ProfileBuilder::hash($profile), 'reasons' => array_slice($reasons, 0, 2), 'limitations' => array_values(array_unique($limitations)), 'evidence' => $profile['evidence'], 'mode' => $exploration ? 'exploration' : 'personal'];
    }

    private function familyLabel(string $family): string
    {
        return ['citrus' => 'citrus/segar', 'aquatic' => 'aquatic', 'green_herbal' => 'hijau/herbal', 'fruit' => 'buah', 'floral' => 'bunga', 'wood' => 'kayu', 'gourmand' => 'gourmand', 'amber_resin' => 'amber/resin', 'oud' => 'oud', 'leather_smoky' => 'leather/asap', 'musk' => 'musk', 'powdery' => 'powdery'][$family];
    }

    private function attributeLabel(string $key): string
    {
        return ['sweetness' => 'kemanisan', 'projection' => 'sebaran', 'longevity' => 'ketahanan', 'context' => 'konteks pemakaian'][$key];
    }

    private function compare(array $a, array $b): int
    {
        return $b['score_internal'] <=> $a['score_internal']
            ?: $b['evidence_completeness'] <=> $a['evidence_completeness']
            ?: (int) ($b['availability'] === 'available') <=> (int) ($a['availability'] === 'available')
            ?: $a['price_minor'] <=> $b['price_minor']
            ?: $a['product_id'] <=> $b['product_id'];
    }
}
