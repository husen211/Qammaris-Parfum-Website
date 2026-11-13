<?php

namespace App\Actions\Orders;

use App\Http\Requests\Cart\CheckoutRequest;
use App\Services\BiteshipClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ORD-13: courier prices for checkout. Biteship gives the courier's price; the store's discount turns it into the
 * charged ongkir. Every option shown is kept on the server for a short while, bound to this visitor's checkout and the
 * cart it was priced for, so the order uses the server's price and never a number sent by the browser.
 *
 * Live 2026-11-13: options are cached one by one, not kept as a list in the session. The checkout asks for instant
 * prices, J&T prices and the pin's kecamatan at the same time; each request saves the whole session it read when it
 * started, so a list in the session lost the options another request had just added and the order was refused.
 */
class ShippingQuotes
{
    /** Biteship courier code → order courier/provider key (OnlineOrder::COURIERS). */
    private const PROVIDERS = ['gojek' => 'gosend', 'grab' => 'grab', 'jnt' => 'jnt'];

    public function __construct(private readonly BiteshipClient $biteship) {}

    public static function enabled(): bool
    {
        return (bool) config('orders.shipping_quotes.enabled') && CheckoutRequest::savesOrder() && app(BiteshipClient::class)->configured();
    }

    /** @return list<array<string, mixed>> one option per instant courier that reaches the pin */
    public function instant(array $items, string $cart, float $latitude, float $longitude): array
    {
        $origin = config('orders.shipping_quotes.origin');
        $couriers = config('orders.shipping_quotes.instant.couriers');
        $rates = $this->biteship->rates([
            'origin_latitude' => $origin['latitude'], 'origin_longitude' => $origin['longitude'],
            'destination_latitude' => $latitude, 'destination_longitude' => $longitude,
            'couriers' => implode(',', array_keys($couriers)),
            'items' => $this->items($items),
        ]);
        $destination = ['latitude' => round($latitude, 7), 'longitude' => round($longitude, 7), 'distance_km' => self::distanceKm($origin, $latitude, $longitude)];
        $options = [];
        foreach ($couriers as $courier => $label) {
            $rate = collect($rates)->first(fn ($rate) => $rate['courier'] === $courier && $rate['service'] === 'instant');
            if ($rate) {
                $options[] = $this->remember('instant', $courier, $label, $rate, $items, $cart, $destination);
            }
        }

        return $options;
    }

    /** @return list<array<string, mixed>> the J&T option for the destination area, or none */
    public function jnt(array $items, string $cart, string $areaId, string $areaLabel): array
    {
        $rates = collect($this->biteship->rates([
            'origin_postal_code' => config('orders.shipping_quotes.origin.postal_code'),
            'destination_area_id' => $areaId,
            'couriers' => 'jnt',
            'items' => $this->items($items),
        ]))->where('courier', 'jnt');
        $rate = $rates->firstWhere('service', 'ez') ?? $rates->sortBy('price')->first();

        return $rate ? [$this->remember('jnt', 'jnt', 'J&T', $rate, $items, $cart, ['area_id' => $areaId, 'area_label' => $areaLabel])] : [];
    }

    /** The stored option the customer picked, if it still belongs to this cart and delivery type. */
    public function resolve(string $id, string $cart, string $fulfillment): ?array
    {
        $quote = $this->stored($id);
        $kind = ['local_delivery' => 'instant', 'intercity' => 'jnt'][$fulfillment] ?? null;

        return $quote && $quote['kind'] === $kind && hash_equals($quote['cart'], $cart) ? $quote : null;
    }

    /**
     * Owner 2026-11-11: instant ongkir from Rp20.000 gets 15% off; J&T 30% off, at most Rp30.000. The charged amount
     * is rounded down to Rp500, so rounding only ever adds to the customer's discount.
     *
     * @return array{charged: int, discount: int, percent: int}
     */
    public static function discounted(string $kind, int $price): array
    {
        $rule = config("orders.shipping_quotes.{$kind}");
        $percent = (int) ($rule['discount_percent'] ?? 0);
        if ($percent <= 0 || $price < (int) ($rule['discount_from'] ?? 0)) {
            return ['charged' => $price, 'discount' => 0, 'percent' => 0];
        }
        $cut = intdiv($price * $percent, 100);
        if (($rule['discount_max'] ?? null) !== null) {
            $cut = min($cut, (int) $rule['discount_max']);
        }
        $step = max(1, (int) config('orders.shipping_quotes.round_down_to', 1));
        $charged = intdiv($price - $cut, $step) * $step;

        return ['charged' => $charged, 'discount' => $price - $charged, 'percent' => $percent];
    }

    /** A stored option of this cart, to show the customer's choice again after a failed submit. */
    public function remembered(string $id, string $cart): ?array
    {
        $quote = $this->stored($id);

        return $quote && hash_equals($quote['cart'], $cart) ? $quote : null;
    }

    /** What the browser may see of a stored option (never the pin itself). */
    public static function public(array $quote): array
    {
        return array_intersect_key($quote, array_flip(['id', 'kind', 'provider', 'label', 'eta', 'price', 'charged', 'discount', 'percent']))
            + ['distance_km' => $quote['destination']['distance_km'] ?? null, 'area_label' => $quote['destination']['area_label'] ?? null];
    }

    /** Straight-line distance from the store, for the customer's own check of the pin. */
    public static function distanceKm(array $origin, float $latitude, float $longitude): float
    {
        $rad = M_PI / 180;
        $a = sin(($latitude - $origin['latitude']) * $rad / 2) ** 2
            + cos($origin['latitude'] * $rad) * cos($latitude * $rad) * sin(($longitude - $origin['longitude']) * $rad / 2) ** 2;

        return round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }

    private function remember(string $kind, string $courier, string $label, array $rate, array $items, string $cart, array $destination): array
    {
        $quote = [
            'id' => Str::random(20),
            'kind' => $kind,
            'provider' => self::PROVIDERS[$courier],
            'label' => $label,
            'service' => $rate['service'],
            'eta' => $this->eta($rate),
            'price' => $rate['price'],
            ...self::discounted($kind, $rate['price']),
            'destination' => $destination,
            'weight_grams' => array_sum(array_column($this->items($items), 'quantity')) * (int) config('orders.shipping_quotes.item_weight_grams'),
            'quoted_at' => now()->toIso8601String(),
            'cart' => $cart,
            'expires_at' => now()->addMinutes((int) config('orders.shipping_quotes.quote_minutes', 30))->getTimestamp(),
        ];
        if ($key = self::cacheKey($quote['id'])) {
            Cache::put($key, $quote, now()->setTimestamp($quote['expires_at']));
        }

        return $quote;
    }

    /** An unexpired option this session was shown. */
    private function stored(string $id): ?array
    {
        $key = preg_match('/^[A-Za-z0-9]{20}$/', $id) ? self::cacheKey($id) : null;
        $quote = $key ? Cache::get($key) : null;

        return is_array($quote) && ($quote['expires_at'] ?? 0) > now()->getTimestamp() ? $quote : null;
    }

    /** Bound to this checkout's key (set when the checkout page opens, never by the parallel price requests). */
    private static function cacheKey(string $id): ?string
    {
        $checkout = session('checkout_key');

        return is_string($checkout) && $checkout !== '' ? 'shipping-quote:'.hash('sha256', $checkout).':'.$id : null;
    }

    private function items(array $items): array
    {
        $weight = (int) config('orders.shipping_quotes.item_weight_grams');

        return array_map(fn (array $item) => [
            'name' => mb_substr((string) $item['product_name'], 0, 80),
            'value' => (int) round((float) $item['price']),
            'weight' => $weight,
            'quantity' => (int) $item['quantity'],
        ], array_values($items));
    }

    private function eta(array $rate): ?string
    {
        $unit = ['hours' => 'jam', 'hour' => 'jam', 'days' => 'hari', 'day' => 'hari'][strtolower((string) $rate['unit'])] ?? null;
        if (! $rate['duration'] || ! $unit) {
            return null;
        }

        return 'Tiba '.preg_replace('/\s*-\s*/', '–', trim($rate['duration'])).' '.$unit;
    }
}
