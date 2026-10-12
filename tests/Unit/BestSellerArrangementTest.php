<?php

namespace Tests\Unit;

use App\Support\BestSellerArrangement;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BestSellerArrangementTest extends TestCase
{
    /** 18 best sellers between Rp189.000 and Rp780.000, ids 1..18 in ascending price. */
    private function prices(): array
    {
        $values = [189000, 199000, 215000, 229000, 245000, 265000, 275000, 289000, 299000, 325000, 345000, 375000, 410000, 450000, 520000, 620000, 700000, 780000];

        return array_combine(range(1, count($values)), $values);
    }

    public function test_every_product_appears_once_and_the_order_is_not_by_price(): void
    {
        $order = BestSellerArrangement::order($this->prices(), CarbonImmutable::parse('2026-10-12 09:00', 'Asia/Makassar'));
        $this->assertEqualsCanonicalizing(range(1, 18), $order);
        $prices = array_map(fn ($id) => $this->prices()[$id], $order);
        $ascending = $prices;
        sort($ascending);
        $this->assertNotSame($ascending, $prices);
        $descending = array_reverse($ascending);
        $this->assertNotSame($descending, $prices);
    }

    public function test_affordable_products_lead_but_premium_ones_appear_early(): void
    {
        foreach (range(1, 14) as $offset) {
            $order = BestSellerArrangement::order($this->prices(), CarbonImmutable::parse('2026-10-01')->addDays($offset));
            $firstSix = array_slice($order, 0, 6);
            // Lowest third = ids 1..6, highest third = ids 13..18.
            $this->assertCount(3, array_filter($firstSix, fn ($id) => $id <= 6), 'three affordable picks in the first six');
            $this->assertCount(1, array_filter($firstSix, fn ($id) => $id >= 13), 'one premium pick in the first six');
            $this->assertLessThanOrEqual(6, $order[0], 'the first product is an affordable one');
        }
    }

    public function test_order_is_stable_within_a_day_and_changes_between_days(): void
    {
        $morning = BestSellerArrangement::order($this->prices(), CarbonImmutable::parse('2026-10-12 00:30', 'Asia/Makassar'));
        $evening = BestSellerArrangement::order($this->prices(), CarbonImmutable::parse('2026-10-12 23:30', 'Asia/Makassar'));
        $this->assertSame($morning, $evening);

        $days = collect(range(0, 6))->map(fn ($day) => implode(',', BestSellerArrangement::order($this->prices(), CarbonImmutable::parse('2026-10-12')->addDays($day))));
        $this->assertGreaterThan(5, $days->unique()->count(), 'a different arrangement on most days');
    }

    public function test_products_without_a_price_go_last_and_small_sets_work(): void
    {
        $order = BestSellerArrangement::order([7 => null, 3 => 250000, 9 => 0, 5 => 400000], CarbonImmutable::parse('2026-10-12'));
        $this->assertSame([7, 9], array_slice($order, -2));
        $this->assertEqualsCanonicalizing([3, 5], array_slice($order, 0, 2));
        $this->assertSame([], BestSellerArrangement::order([], CarbonImmutable::parse('2026-10-12')));
        $this->assertSame([4], BestSellerArrangement::order([4 => 300000], CarbonImmutable::parse('2026-10-12')));
    }
}
