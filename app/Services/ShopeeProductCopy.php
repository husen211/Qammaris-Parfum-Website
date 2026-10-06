<?php

namespace App\Services;

class ShopeeProductCopy
{
    public function clean(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', $raw) as $line) {
            if (preg_match('/\b(?:ongkir|ongkos\s+kirim|free\s+shipping|shipping\s+fee|biaya\s+kirim)\b/iu', $line)) {
                continue;
            }
            $line = preg_replace('/[\x{1F100}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0E}\x{FE0F}\x{200D}\x{20E3}]|\bready[\s-]*stock\b|(?<!\w)#[\w-]+/iu', '', $line);
            $lines[] = trim(preg_replace('/[\t ]+/', ' ', $line));
        }

        return trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)));
    }

    public function gender(string $name, string $description): ?string
    {
        $found = [];
        foreach (array_merge([$name], preg_split('/\R/u', $description)) as $line) {
            if (preg_match('/\b(?:inspirasi|inspired|dibanding|mirip|similar)\b/iu', $line)) {
                continue;
            }
            $pair = preg_match('/\b(?:pria\s*(?:dan|&|\/)\s*wanita|wanita\s*(?:dan|&|\/)\s*pria|men\s*(?:and|&|\/)\s*women)\b/iu', $line);
            if (preg_match('/\bunisex\b/iu', $line) || $pair) {
                $found['Unisex'] = true;
            }
            if (! $pair && preg_match('/\b(?:pour\s+homme|for\s+(?:men|man|him)|(?:untuk|parfum|gender\s*:)\s*(?:pria|men|man))\b/iu', $line)) {
                $found['Pria'] = true;
            }
            if (! $pair && preg_match('/\b(?:pour\s+femme|for\s+(?:women|woman|her)|(?:untuk|parfum|gender\s*:)\s*(?:wanita|women|woman))\b/iu', $line)) {
                $found['Wanita'] = true;
            }
        }

        if (preg_match('/\b(?:homme|for\s+(?:men|man|him))\b/iu', $name)) {
            $found['Pria'] = true;
        }
        if (preg_match('/\b(?:woman|women|femme|for\s+her)\b/iu', $name)) {
            $found['Wanita'] = true;
        }

        return count($found) === 1 ? array_key_first($found) : null;
    }
}
