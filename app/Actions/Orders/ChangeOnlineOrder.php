<?php

namespace App\Actions\Orders;

use App\Actions\Orders\Concerns\MutatesV2Order;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\OrderValidationFailed;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\OnlineOrderMoney;
use App\Support\Rupiah;
use Illuminate\Support\Facades\Gate;

/**
 * ORD-27 (Owner 2026-11-17): staff change an order after it was made — swap a sold-out product, change quantities,
 * add or remove products, or switch how it is delivered — before it is handed over, also after payment. Kept lines
 * keep their price; new products take today's catalog price. The total follows, and the ledger decides what is
 * left to pay or what goes back to the customer; nothing is assumed paid or refunded.
 */
class ChangeOnlineOrder
{
    use MutatesV2Order;

    /**
     * @param  list<array{line_id?: ?string, variant_id: int, quantity: int}>  $lines  the order's lines after the change
     * @param  array{fulfillment: string, address?: ?string, postcode?: ?string, location_url?: ?string, shipping_fee?: ?string, shipping_payer?: ?string, quote?: ?array, area_label?: ?string}  $shipping
     */
    public function handle(OnlineOrder $order, int $revision, User $actor, array $lines, array $shipping, ?string $note = null): OnlineOrder
    {
        abort_unless($actor->exists && Gate::forUser($actor)->allows('orders.manage'), 403);

        return $this->mutateV2($order, $revision, $actor, function (OnlineOrder $order) use ($actor, $lines, $shipping, $note): bool {
            self::assertChangeable($order);
            $before = ['total' => Rupiah::minorUnits($order->customerTotal()), 'lines' => $order->items->map(fn ($item) => $item->quantity.'× '.$item->label())->all(), 'shipping' => self::shippingLabel($order)];

            $linesChanged = $this->replaceLines($order, $lines);
            $shippingChanged = $this->applyShipping($order, $shipping);
            if (! $linesChanged && ! $shippingChanged) {
                return false;
            }
            // Other products in the bag: it is packed again.
            if ($linesChanged && $order->preparation_status !== 'not_started') {
                $order->preparation_status = 'not_started';
            }
            $order->unsetRelation('items');
            $after = Rupiah::minorUnits($order->customerTotal());
            $money = OnlineOrderMoney::totals($order);
            $paid = $money['received'] - $money['refunded'];
            $parts = [];
            if ($linesChanged) {
                $parts[] = 'Produk: '.implode(', ', $before['lines']).' → '.$order->items->map(fn ($item) => $item->quantity.'× '.$item->label())->join(', ');
            }
            if ($shippingChanged && $before['shipping'] !== self::shippingLabel($order)) {
                $parts[] = 'Pengiriman: '.$before['shipping'].' → '.self::shippingLabel($order);
            }
            $parts[] = 'total '.Rupiah::format(Rupiah::decimal($before['total'])).' → '.Rupiah::format(Rupiah::decimal($after));
            if ($paid > 0 && $after > $paid) {
                $parts[] = 'kurang bayar '.Rupiah::format(Rupiah::decimal($after - $paid));
            } elseif ($paid > $after) {
                $parts[] = 'lebih bayar '.Rupiah::format(Rupiah::decimal($paid - $after));
            }
            if ($note !== null && trim($note) !== '') {
                $parts[] = trim($note);
            }
            $this->orderEvent($order, $actor, 'order_changed', implode(' · ', $parts));

            return true;
        });
    }

    /** Whether staff may still change the order: open, not handed over, and no Biteship driver on the way. */
    public static function changeable(OnlineOrder $order): bool
    {
        try {
            self::assertChangeable($order);

            return true;
        } catch (InvalidOrderTransition) {
            return false;
        }
    }

    /** "J&T", "GoSend · kurir instan", "Ambil di toko"… for history and the customer message. */
    public static function shippingLabel(OnlineOrder $order): string
    {
        $label = $order->shipping_quote['label'] ?? null;

        return match ($order->fulfillment) {
            'pickup' => 'Ambil di toko',
            'local_delivery' => $label ? $label.' (kurir instan)' : 'Kurir dalam kota',
            'intercity' => $label ?? 'Kirim ke luar kota',
            default => 'Belum dipilih',
        };
    }

    private static function assertChangeable(OnlineOrder $order): void
    {
        if (! $order->isV2() || ! in_array($order->lifecycle, ['draft', 'awaiting_customer', 'active'], true)) {
            throw new InvalidOrderTransition('Pesanan yang sudah selesai atau dibatalkan tidak dapat diubah.');
        }
        if ($order->handover_status !== 'pending') {
            throw new InvalidOrderTransition('Barang sudah diserahkan, jadi pesanan tidak dapat diubah lagi.');
        }
        if ($order->biteshipBusy()) {
            throw new InvalidOrderTransition('Kurir Biteship masih dipesan. Batalkan kurir dulu di bagian Pengiriman, lalu ubah pesanan.');
        }
    }

    /** @return bool whether any line changed */
    private function replaceLines(OnlineOrder $order, array $lines): bool
    {
        if ($lines === []) {
            throw new OrderValidationFailed('Pesanan harus berisi minimal satu produk. Untuk membatalkan, pakai Batalkan pesanan.', ['items' => 'Pilih produk']);
        }
        $existing = $order->items()->get()->keyBy('line_id');
        $newVariantIds = collect($lines)->filter(fn ($line) => empty($line['line_id']))->pluck('variant_id')->all();
        $variants = $newVariantIds === [] ? collect() : ProductVariant::with('product.brand')->active()
            ->whereHas('product', fn ($product) => $product->orderableByAdmin())->whereIn('id', $newVariantIds)->get()->keyBy('id');

        $seen = [];
        $kept = [];
        $changed = false;
        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            if ($quantity < 1 || $quantity > 99) {
                throw new OrderValidationFailed('Jumlah tiap produk 1 sampai 99.', ['items' => 'Jumlah tidak valid']);
            }
            if (! empty($line['line_id'])) {
                // A line of another order is never touched (child IDs are scoped to their order).
                $item = $existing->get($line['line_id']);
                if (! $item) {
                    throw new OrderValidationFailed('Ada produk yang sudah berubah di pesanan ini. Muat ulang halaman, lalu ulangi.', ['items' => 'Baris tidak dikenal']);
                }
                $variantId = (int) $item->variant_id;
                $kept[] = $item->line_id;
                if ($item->quantity !== $quantity) {
                    $item->update(['quantity' => $quantity]);
                    $changed = true;
                }
            } else {
                $variant = $variants->get((int) $line['variant_id']);
                if (! $variant || Rupiah::minorUnits($variant->price) <= 0) {
                    throw new OrderValidationFailed('Ada produk yang tidak lagi tersedia di katalog atau belum punya harga. Pilih produk lain.', ['items' => 'Produk tidak tersedia']);
                }
                $variantId = (int) $variant->id;
                $order->items()->create([
                    'product_id' => $variant->product_id, 'variant_id' => $variant->id,
                    'brand_name' => $variant->product->brand?->name ?? 'Brand belum diisi', 'product_name' => $variant->product->name,
                    'volume' => $variant->volume, 'unit_price' => $variant->price, 'quantity' => $quantity, 'weight_grams' => $variant->weight_grams,
                ]);
                $changed = true;
            }
            if (isset($seen[$variantId])) {
                throw new OrderValidationFailed('Produk yang sama muncul dua kali. Ubah jumlahnya saja.', ['items' => 'Produk dobel']);
            }
            $seen[$variantId] = true;
        }
        $removed = $existing->keys()->diff($kept);
        if ($removed->isNotEmpty()) {
            OnlineOrderItem::where('online_order_id', $order->id)->whereIn('line_id', $removed->all())->delete();
            $changed = true;
        }

        return $changed;
    }

    /** @return bool whether delivery or ongkir changed */
    private function applyShipping(OnlineOrder $order, array $shipping): bool
    {
        $fulfillment = $shipping['fulfillment'];
        $order->fulfillment = $fulfillment;
        $order->address = $fulfillment === 'pickup' ? null : ($shipping['address'] ?? null);
        $order->postcode = $fulfillment === 'intercity' ? ($shipping['postcode'] ?? null) : null;
        if (array_key_exists('location_url', $shipping) && $fulfillment === 'local_delivery') {
            $order->location_url = $shipping['location_url'];
        }

        if ($fulfillment === 'pickup') {
            $order->shipping_quote = null;
            $order->shipping_fee = null;
            $order->shipping_payer = null;
            $order->shipping_estimate = null;
            $order->shipping_estimate_source = null;
        } elseif (! empty($shipping['quote'])) {
            $order->applyShippingQuote($shipping['quote'], $shipping['area_label'] ?? null, 'staff');
        } else {
            $fee = $shipping['shipping_fee'] ?? null;
            $payer = $shipping['shipping_payer'] ?? null;
            if ($fee !== null && $payer === null) {
                $payer = 'added_to_transfer';
            }
            $priceChanged = ($fee === null ? null : Rupiah::minorUnits($fee)) !== ($order->shipping_fee === null ? null : Rupiah::minorUnits($order->shipping_fee))
                || $payer !== $order->shipping_payer || $order->isDirty('fulfillment');
            if ($priceChanged) {
                // A price typed by staff replaces the checkout's Biteship price; its pin stays as the delivery pin.
                if (($order->shipping_quote['kind'] ?? null) === 'instant' && $fulfillment === 'local_delivery' && ! $order->isDirty('location_url')
                    && ($pin = $order->deliveryPin())) {
                    $order->location_url = 'https://www.google.com/maps?q='.$pin['latitude'].','.$pin['longitude'];
                }
                $order->shipping_quote = null;
                $order->shipping_estimate = null;
                $order->shipping_estimate_source = null;
                $order->shipping_fee = $fee === null ? null : Rupiah::decimal(Rupiah::minorUnits($fee));
                $order->shipping_payer = $payer;
            }
        }
        // A delivery that is no longer by courier, or changed, needs its courier booked again.
        if ($order->isDirty('fulfillment') && in_array($order->courier_status, ['requested', 'assigned'], true)) {
            $order->courier_status = $fulfillment === 'local_delivery' ? 'unassigned' : 'not_needed';
            $order->courier_provider = null;
        }

        return $order->isDirty(['fulfillment', 'address', 'postcode', 'location_url', 'shipping_fee', 'shipping_payer', 'shipping_quote']);
    }
}
