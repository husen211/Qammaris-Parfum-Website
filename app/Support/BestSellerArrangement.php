<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * UX-02: order of best sellers in the default "Terlaris" catalog sort.
 *
 * Affordable best sellers lead, with mid-range and premium ones mixed in, so the first screen does not read as
 * "everything is expensive". Never sorted by price: products are bucketed into price thirds, shuffled per Palu
 * calendar day (stable within the day, different the next day), then interleaved low, mid, low, high, low, mid.
 */
final class BestSellerArrangement
{
    private const PATTERN = ['low', 'mid', 'low', 'high', 'low', 'mid'];

    /**
     * @param  array<int, int|float|string|null>  $prices  product id => lowest active price (null = no price)
     * @return list<int> product ids in display order
     */
    public static function order(array $prices, CarbonInterface $day): array
    {
        $seed = $day->copy()->timezone('Asia/Makassar')->toDateString();
        $priced = array_filter($prices, fn ($price) => $price !== null && (float) $price > 0);
        $unpriced = array_keys(array_diff_key($prices, $priced));
        sort($unpriced);

        asort($priced);
        $ids = array_keys($priced);
        $count = count($ids);
        $buckets = ['low' => [], 'mid' => [], 'high' => []];
        foreach ($ids as $rank => $id) {
            $buckets[match (true) {
                $rank < intdiv($count + 2, 3) => 'low',
                $rank < intdiv(2 * $count + 2, 3) => 'mid',
                default => 'high',
            }][] = $id;
        }
        foreach ($buckets as $name => $members) {
            usort($members, fn (int $a, int $b) => strcmp(md5("{$seed}|{$a}"), md5("{$seed}|{$b}")) ?: $a <=> $b);
            $buckets[$name] = $members;
        }

        $ordered = [];
        $fallback = ['low', 'mid', 'high'];
        for ($slot = 0; count($ordered) < $count; $slot++) {
            $want = self::PATTERN[$slot % count(self::PATTERN)];
            $from = $buckets[$want] !== [] ? $want : current(array_filter($fallback, fn ($name) => $buckets[$name] !== []));
            $ordered[] = array_shift($buckets[$from]);
        }

        return [...$ordered, ...$unpriced];
    }
}
