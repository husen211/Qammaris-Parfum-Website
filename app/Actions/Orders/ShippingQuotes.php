<?php

namespace App\Actions\Orders;

use App\Http\Requests\Cart\CheckoutRequest;
use App\Models\OnlineOrder;
use App\Services\BiteshipClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ORD-13: courier prices for checkout. Biteship gives the courier's price; the store's discount turns it into the
 * charged ongkir. Every option shown is kept on the server for a short while, bound to this visitor's checkout and the
 * cart it was priced for, so the order uses the server's price and never a number sent by the browser.
 *
 * Live 2026-11-13: options are cached one by one, not kept as a list in the session. The checkout asks for instant
 * prices, J&T prices and the pin's kecamatan at the same time; each request saves the whole session it read when it
 * started, so a list in the session lost the options another request had just added and the order was refused.
 *
 * ORD-26: the same prices on an order link (admin-made and WhatsApp orders), kept per order instead of per checkout.
 */
class ShippingQuotes
{
    /** Biteship courier code → order courier/provider key (OnlineOrder::COURIERS). */
    private const PROVIDERS = ['gojek' => 'gosend', 'grab' => 'grab', 'jnt' => 'jnt'];

    /** Whose options these are: this visitor's checkout (null) or one order ("order:<id>"). */
    private ?string $scope = null;

    public function __construct(private readonly BiteshipClient $biteship) {}

    public static function enabled(): bool
    {
        return self::pricesOrders() && CheckoutRequest::savesOrder();
    }

    /** ORD-26: prices for an existing order need Biteship, not the website checkout. */
    public static function pricesOrders(): bool
    {
        return (bool) config('orders.shipping_quotes.enabled') && app(BiteshipClient::class)->configured();
    }

    /** ORD-26: the same pricing for one order: options kept for that order and its current lines only. */
    public function forOrder(OnlineOrder $order): static
    {
        $copy = clone $this;
        $copy->scope = 'order:'.$order->id;

        return $copy;
    }

    /** @return list<array{product_name: string, price: string, quantity: int}> */
    public static function orderItems(OnlineOrder $order): array
    {
        return $order->items->map(fn ($item) => ['product_name' => (string) $item->product_name, 'price' => (string) $item->unit_price, 'quantity' => (int) $item->quantity])->all();
    }

    /** The order's lines as priced; a changed line makes its earlier options invalid, like a changed cart. */
    public static function orderCart(OnlineOrder $order): string
    {
        return hash('sha256', json_encode($order->items->map(fn ($item) => [$item->variant_id, (int) $item->quantity, (string) $item->unit_price])->all(), JSON_THROW_ON_ERROR));
    }

    /** Validation of a price request from the checkout or an order link. */
    public static function requestRules(): array
    {
        return [
            'mode' => ['required', 'in:instant,jnt'],
            'latitude' => ['exclude_unless:mode,instant', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['exclude_unless:mode,instant', 'required', 'numeric', 'between:-180,180'],
            'area_id' => ['exclude_unless:mode,jnt', 'required', 'string', 'regex:/^[A-Za-z0-9]{1,64}$/'],
            'area_label' => ['exclude_unless:mode,jnt', 'nullable', 'string', 'max:160'],
        ];
    }

    /**
     * The answer to a validated price request: the options the browser may see, and why there are none.
     *
     * @return array{options: list<array<string, mixed>>, reason: ?string}
     */
    public function answer(array $data, array $items, string $cart): array
    {
        $area = config('orders.shipping_quotes.service_area');
        // The label staff ship to must be the one Biteship gave for this area id, not text sent by the browser.
        $areaLabel = $data['mode'] === 'jnt' ? self::areaLabel($data['area_id']) : null;
        if ($data['mode'] === 'jnt' && $areaLabel === null) {
            return ['options' => [], 'reason' => 'unavailable'];
        }
        if ($data['mode'] === 'instant' && ((float) $data['latitude'] < $area['south'] || (float) $data['latitude'] > $area['north']
            || (float) $data['longitude'] < $area['west'] || (float) $data['longitude'] > $area['east'])) {
            return ['options' => [], 'reason' => 'outside_area'];
        }
        try {
            $options = $data['mode'] === 'instant'
                ? $this->instant($items, $cart, (float) $data['latitude'], (float) $data['longitude'])
                : $this->jnt($items, $cart, $data['area_id'], $areaLabel);
        } catch (RuntimeException) {
            return ['options' => [], 'reason' => 'unavailable'];
        }

        return ['options' => array_map([self::class, 'public'], $options), 'reason' => $options ? null : 'no_courier'];
    }

    /** ORD-18: Biteship's own label for an area id someone searched; never text from the browser. */
    public static function areaLabel(string $id): ?string
    {
        return $id === '' ? null : Cache::get('biteship-area-label:'.$id);
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
        return $this->jntTo(['destination_area_id' => $areaId], $items, $cart, ['area_id' => $areaId, 'area_label' => $areaLabel]);
    }

    /** ORD-26: the J&T option for an order staff filled with a postcode (no area searched). */
    public function jntToPostcode(array $items, string $cart, string $postcode): array
    {
        return $this->jntTo(['destination_postal_code' => $postcode], $items, $cart, ['postal_code' => $postcode]);
    }

    private function jntTo(array $destination, array $items, string $cart, array $kept): array
    {
        $rates = collect($this->biteship->rates([
            'origin_postal_code' => config('orders.shipping_quotes.origin.postal_code'),
            ...$destination,
            'couriers' => 'jnt',
            'items' => $this->items($items),
        ]))->where('courier', 'jnt');
        $rate = $rates->firstWhere('service', 'ez') ?? $rates->sortBy('price')->first();

        return $rate ? [$this->remember('jnt', 'jnt', 'J&T', $rate, $items, $cart, $kept)] : [];
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
        if ($key = $this->cacheKey($quote['id'])) {
            Cache::put($key, $quote, now()->setTimestamp($quote['expires_at']));
        }

        return $quote;
    }

    /** An unexpired option this session was shown. */
    private function stored(string $id): ?array
    {
        $key = preg_match('/^[A-Za-z0-9]{20}$/', $id) ? $this->cacheKey($id) : null;
        $quote = $key ? Cache::get($key) : null;

        return is_array($quote) && ($quote['expires_at'] ?? 0) > now()->getTimestamp() ? $quote : null;
    }

    /** Bound to this checkout's key (set when the checkout page opens, never by the parallel price requests), or the order. */
    private function cacheKey(string $id): ?string
    {
        $checkout = $this->scope ?? session('checkout_key');

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
