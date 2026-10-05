<?php

namespace App\Services;

use InvalidArgumentException;

class ShopeeProductMediaReview
{
    public function __construct(private ImportedProductName $names) {}

    public function match(array $sources, array $media): array
    {
        $ids = [];
        foreach ($media as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null) || ! ctype_digit($row['id'])
                || isset($ids[$row['id']]) || ! is_string($row['name'] ?? null)
                || ! is_array($row['photos'] ?? null) || count($row['photos']) !== 3) {
                throw new InvalidArgumentException('Invalid or duplicate Shopee media row.');
            }
            foreach ($row['photos'] as $url) {
                if (! is_string($url) || strlen($url) > 2048) {
                    throw new InvalidArgumentException('Invalid media URL.');
                }
            }
            $ids[$row['id']] = true;
        }
        $result = [];
        $owners = [];
        foreach ($sources as $source) {
            $key = $this->names->mediaKey($source['name'], $source['brand'] ?? '');
            $candidates = $key === null ? [] : array_values(array_filter($media,
                fn ($row) => $this->names->mediaKey($row['name'], $source['brand'] ?? '') === $key
                    && ($this->names->concentration($source['name']) === null
                        || $this->names->concentration($row['name']) === null
                        || $this->names->concentration($source['name']) === $this->names->concentration($row['name']))));
            $result[$source['id']] = ['status' => count($candidates) === 1 ? 'strong' : (count($candidates) > 1 ? 'ambiguous' : 'unmatched'),
                'candidates' => array_column($candidates, 'id'), 'media' => count($candidates) === 1 ? $candidates[0] : null];
            if ($candidates === []) {
                // Similarity is review assistance only; it can never authorize a media write.
                $name = $this->names->reviewName($source['name'], $source['brand'] ?? '');
                $suggestions = array_filter($media, function ($row) use ($name, $source): bool {
                    $other = $this->names->reviewName($row['name'], $source['brand'] ?? '');
                    $size = $this->names->size($source['name']);
                    $otherSize = $this->names->size($row['name']);
                    if (strlen($name) < 5 || ($size !== null && $otherSize !== null && $size !== $otherSize)) {
                        return false;
                    }
                    similar_text($name, $other, $percent);

                    return $percent >= 82;
                });
                if ($suggestions !== []) {
                    $result[$source['id']]['status'] = 'manual_review';
                    $result[$source['id']]['candidates'] = array_values(array_column($suggestions, 'id'));
                }
            }
            if (count($candidates) === 1) {
                $owners[$candidates[0]['id']][] = $source['id'];
            }
        }
        foreach ($owners as $sourceIds) {
            if (count($sourceIds) > 1) {
                foreach ($sourceIds as $id) {
                    $result[$id]['status'] = 'ambiguous';
                    $result[$id]['media'] = null;
                }
            }
        }

        return $result;
    }
}
