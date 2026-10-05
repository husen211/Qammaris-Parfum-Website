<?php

namespace App\Services;

use Illuminate\Support\Str;

class ImportedProductName
{
    public function size(string $name): ?int
    {
        preg_match_all('/(?<![\d.,])(\d+(?:[.,]\d+)?)\s*ml\b/iu', $name, $matches);
        if (count($matches[1]) !== 1 || preg_match('/\b(?:set|bundle|refill)\b|\d+\s*[x×]/iu', $name)) {
            return null;
        }
        $size = (float) str_replace(',', '.', $matches[1][0]);

        // The current offer column stores integer ml. Never round an imported size.
        return $size >= 1 && $size <= 10000 && floor($size) === $size ? (int) $size : null;
    }

    public function concentration(string $name): ?string
    {
        $matches = [];
        foreach ([
            'Extrait de Parfum' => '/\b(?:extrait(?: de parfum)?|ext)\b/iu',
            'Eau de Parfum' => '/\b(?:eau de parfum|edp)\b/iu',
            'Eau de Toilette' => '/\b(?:eau de toilette|edt)\b/iu',
            'Perfume Oil' => '/\b(?:perfume oil|parfum oil)\b/iu',
        ] as $value => $pattern) {
            if (preg_match($pattern, $name)) {
                $matches[] = $value;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    public function mediaKey(string $name, string $brand): ?string
    {
        $size = $this->size($name);
        if ($size === null || trim($brand) === '') {
            return null;
        }
        $name = $this->reviewName($name, $brand);

        return $name !== '' ? $this->normalize($brand).'|'.$name.'|'.$size : null;
    }

    public function reviewName(string $name, string $brand): string
    {
        $name = preg_replace('/\d+(?:[.,]\d+)?\s*ml\b|\b(?:extrait(?: de parfum)?|eau de parfum|eau de toilette|edp|edt|ext|perfume oil|parfum oil)\b/iu', ' ', $name);
        $name = $this->normalize($name);
        $brand = $this->normalize($brand);
        if (str_starts_with($name, $brand.' ')) {
            $name = substr($name, strlen($brand) + 1);
        }

        return $name;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($value))));
    }
}
