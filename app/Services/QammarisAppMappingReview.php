<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

class QammarisAppMappingReview
{
    public const HEADERS = [
        'catalog_label', 'product_id', 'slug', 'publication_status', 'website_name', 'website_brand',
        'website_size_ml', 'website_sku', 'website_price', 'review_status', 'reason', 'candidate_count',
        'source_uuid', 'source_name', 'source_brand', 'source_size_ml', 'source_sku', 'source_revision',
        'source_hidden', 'source_availability', 'source_department', 'source_price_proposal',
        'price_difference_proposal', 'catalog_issues', 'media_issues', 'row_fingerprint',
    ];

    /**
     * Generate review suggestions only. Neither a name/size match nor a SKU match authorizes mapping.
     *
     * @param  array<int, array<string, mixed>>  $catalog
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, array<string, mixed>>
     */
    public function rows(array $catalog, array $sources, string $label): array
    {
        $byId = [];
        foreach ($sources as $source) {
            if (! is_array($source) || ! Str::isUuid($source['id'] ?? '')
                || ! is_string($source['name'] ?? null) || trim($source['name']) === ''
                || ! is_int($source['revision'] ?? null) || $source['revision'] < 1
                || ! is_bool($source['hidden'] ?? null)
                || ! in_array($source['availability'] ?? null, ['available', 'sold_out', 'unknown'], true)
                || isset($byId[$source['id']])) {
                throw new InvalidArgumentException('Invalid or duplicate source product in review snapshot.');
            }
            $byId[$source['id']] = $source;
        }
        ksort($byId);
        $rows = [];
        foreach ($catalog as $product) {
            $mapped = $product['source_uuid'] ?? null;
            $candidates = [];
            if ($mapped !== null) {
                if (isset($byId[$mapped])) {
                    $candidates[] = [$byId[$mapped], 'existing_uuid_mapping'];
                }
            } else {
                foreach ($byId as $source) {
                    $reason = $this->candidateReason($product, $source);
                    if ($reason !== null) {
                        $candidates[] = [$source, $reason];
                    }
                }
            }
            if ($candidates === []) {
                $rows[] = $this->row($product, null, $label,
                    $mapped !== null ? 'mapped_source_missing' : 'unmatched',
                    $mapped !== null ? 'retain_existing_identity' : 'manual_matching_required', 0);

                continue;
            }
            foreach ($candidates as [$source, $reason]) {
                $status = $mapped !== null ? 'mapped' : (count($candidates) > 1 ? 'ambiguous' : 'candidate_review');
                if ($source['hidden']) {
                    $status = $mapped !== null ? 'mapped_hidden' : 'hidden_review';
                    $reason .= '|source_hidden_not_launchable';
                }
                $rows[] = $this->row($product, $source, $label, $status, $reason, count($candidates));
            }
        }

        // Even individually strong suggestions cannot share one provider UUID across website products.
        $owners = [];
        foreach ($rows as $row) {
            if ($row['source_uuid'] !== '') {
                $owners[$row['source_uuid']][$row['product_id']] = true;
            }
        }
        foreach ($rows as &$row) {
            if ($row['source_uuid'] !== '' && count($owners[$row['source_uuid']]) > 1
                && ! in_array($row['review_status'], ['mapped', 'mapped_hidden'], true)) {
                $row['review_status'] = 'identity_conflict';
                $row['reason'] .= '|uuid_candidate_for_multiple_website_products';
            }
        }
        unset($row);

        return $rows;
    }

    /** @param resource $stream */
    public function write($stream, array $rows, SpreadsheetSafeCell $safeCell): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, self::HEADERS, escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($key) => $safeCell->sanitize($row[$key]), self::HEADERS), escape: '');
        }
    }

    private function candidateReason(array $product, array $source): ?string
    {
        $skuMatch = trim((string) ($product['sku'] ?? '')) !== ''
            && trim((string) ($product['sku'] ?? '')) === trim((string) ($source['sku'] ?? ''));
        $brand = (string) ($product['brand'] ?? '');
        $nameMatch = $this->name($product['name'], $brand) !== ''
            && $this->name($product['name'], $brand) === $this->name($source['name'], $brand);
        if (! $skuMatch && ! $nameMatch) {
            return null;
        }
        $size = $this->size($source['name']);
        $websiteSize = $product['size_ml'] ?? null;
        $sourceBrand = $this->normalize((string) ($source['brand'] ?? ''));
        $websiteBrand = $this->normalize($brand);
        $brandMatch = $websiteBrand !== '' && ($sourceBrand === $websiteBrand
            || ($sourceBrand === '' && str_starts_with($this->normalize($source['name']), $websiteBrand.' ')));
        if ($nameMatch && $brandMatch && $size !== null && $websiteSize !== null && $size === (float) $websiteSize) {
            return 'exact_name_size_brand_requires_owner_review';
        }
        $reasons = [$skuMatch ? 'sku_assistance_only' : 'name_assistance_only'];
        if ($size === null || $websiteSize === null) {
            $reasons[] = 'size_not_confirmed';
        } elseif ($size !== (float) $websiteSize) {
            $reasons[] = 'size_conflict';
        }
        if (! $brandMatch) {
            $reasons[] = 'brand_not_confirmed';
        }
        if (! $nameMatch) {
            $reasons[] = 'name_conflict';
        }
        if (mb_strlen((string) ($source['sku'] ?? '')) >= 50) {
            $reasons[] = 'source_sku_may_be_truncated';
        }

        return implode('|', $reasons);
    }

    private function size(string $name): ?float
    {
        preg_match_all('/(?<![\d.,])(\d+(?:[.,]\d+)?)\s*ml\b/iu', $name, $matches);
        if (count($matches[1]) !== 1 || preg_match('/\b(?:\d+\s*[x×]|set|bundle|refill)\b/iu', $name)) {
            return null;
        }
        $size = (float) str_replace(',', '.', $matches[1][0]);

        return $size > 0 ? $size : null;
    }

    private function name(string $name, string $brand): string
    {
        $name = $this->normalize(preg_replace('/\d+(?:[.,]\d+)?\s*ml\b/iu', ' ', $name));
        $brand = $this->normalize($brand);
        if ($brand !== '' && str_starts_with($name, $brand.' ')) {
            $name = substr($name, strlen($brand) + 1);
        }

        return $name;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($value))));
    }

    private function row(array $product, ?array $source, string $label, string $status, string $reason, int $count): array
    {
        return [
            'catalog_label' => $label, 'product_id' => $product['id'], 'slug' => $product['slug'],
            'publication_status' => $product['publication_status'], 'website_name' => $product['name'],
            'website_brand' => $product['brand'] ?? '', 'website_size_ml' => $product['size_ml'] ?? '',
            'website_sku' => $product['sku'] ?? '', 'website_price' => $product['price'] ?? '',
            'review_status' => $status, 'reason' => $reason, 'candidate_count' => $count,
            'source_uuid' => $source['id'] ?? $product['source_uuid'] ?? '',
            'source_name' => $source['name'] ?? '', 'source_brand' => $source['brand'] ?? '',
            'source_size_ml' => $source ? $this->size($source['name']) : '',
            'source_sku' => $source['sku'] ?? '', 'source_revision' => $source['revision'] ?? '',
            'source_hidden' => $source === null ? '' : ($source['hidden'] ? 'yes' : 'no'),
            'source_availability' => $source['availability'] ?? '', 'source_department' => $source['department_code'] ?? '',
            'source_price_proposal' => $source['price'] ?? '',
            'price_difference_proposal' => isset($source['price'], $product['price'])
                ? number_format($source['price'] - (float) $product['price'], 2, '.', '') : '',
            'catalog_issues' => implode('|', $product['catalog_issues'] ?? []),
            'media_issues' => implode('|', $product['media_issues'] ?? []),
            'row_fingerprint' => hash('sha256', json_encode([$product, $source], JSON_THROW_ON_ERROR)),
        ];
    }
}
