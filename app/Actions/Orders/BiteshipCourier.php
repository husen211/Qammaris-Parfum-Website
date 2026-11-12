<?php

namespace App\Actions\Orders;

use App\Exceptions\InvalidOrderTransition;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\StoreInfo;
use App\Models\User;
use App\Services\BiteshipClient;
use App\Support\OrderActor;
use App\Support\PhoneNumber;
use RuntimeException;

/**
 * ORD-14: the instant courier booked through Biteship from the admin order page. The order lock is held only to
 * reserve and to record the answer, never while Biteship is called. Status changes come from Biteship itself
 * (webhook or "Perbarui status") and are always re-read from Biteship's order, never from the webhook body.
 */
class BiteshipCourier
{
    public function __construct(
        private readonly BiteshipClient $client,
        private readonly OnlineOrderFulfillment $fulfillment,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('orders.shipping_quotes.enabled') && $this->client->configured();
    }

    public function book(OnlineOrder $order, int $revision, User $user, string $courier, bool $checkedDashboard = false): OnlineOrder
    {
        if (! $this->enabled()) {
            throw new InvalidOrderTransition('Pemesanan kurir lewat Biteship belum aktif. Pesan manual di aplikasinya.');
        }
        $actor = OrderActor::user($user);
        $reserved = $this->fulfillment->startBiteshipBooking($order, $revision, $actor, $courier, $checkedDashboard);
        $attempt = (int) $reserved->courier_booking['attempt'];
        try {
            $result = $this->client->createOrder($this->payload($reserved, $courier, $attempt));
        } catch (RuntimeException $failure) {
            $reason = self::reason($failure->getMessage());
            // No answer (or an unreadable one) may still mean Biteship booked the driver.
            $unsure = in_array($failure->getMessage(), ['biteship_unavailable', 'biteship_invalid_order'], true);
            $this->fulfillment->finishBiteshipBooking($reserved, $actor, $attempt, null, $reason, $unsure);
            throw new InvalidOrderTransition(($unsure ? 'Hasil pemesanan belum pasti: ' : 'Kurir belum terpesan: ').$reason);
        }

        return $this->fulfillment->finishBiteshipBooking($reserved, $actor, $attempt, $result);
    }

    /** Re-reads the booked order from Biteship and applies its status (webhook and the "Perbarui status" button). */
    public function refresh(OnlineOrder $order): OnlineOrder
    {
        $id = $order->courier_booking['id'] ?? null;
        if (! is_string($id) || ($order->courier_booking['state'] ?? null) !== 'active') {
            return $order;
        }

        return $this->fulfillment->applyBiteshipStatus($order, $this->client->order($id));
    }

    /** Cancels the driver before pickup; Biteship returns the balance. */
    public function cancel(OnlineOrder $order, User $user, string $reason): OnlineOrder
    {
        $booking = $order->courier_booking ?? [];
        if (($booking['state'] ?? null) !== 'active' || $order->handover_status !== 'pending' || ! is_string($booking['id'] ?? null)) {
            throw new InvalidOrderTransition('Tidak ada kurir Biteship yang bisa dibatalkan.');
        }
        try {
            $result = $this->client->cancelOrder($booking['id'], $reason);
        } catch (RuntimeException $failure) {
            throw new InvalidOrderTransition('Kurir belum dibatalkan: '.self::reason($failure->getMessage()));
        }
        // Biteship answers "cancelled"; the same rule as a webhook frees the courier step, attributed to this admin.
        $result['status'] = 'cancelled';

        return $this->fulfillment->applyBiteshipStatus($order, $result, OrderActor::user($user));
    }

    /** What staff read when Biteship refuses or cannot be reached. */
    public static function reason(string $message): string
    {
        return match ($message) {
            'biteship_unavailable' => 'Biteship tidak menjawab. Cek dashboard Biteship sebelum memesan ulang, supaya tidak dobel.',
            'biteship_not_configured' => 'Kunci API Biteship belum diatur.',
            'biteship_invalid_order' => 'Jawaban Biteship tidak lengkap. Cek dashboard Biteship sebelum memesan ulang.',
            default => $message,
        };
    }

    private function payload(OnlineOrder $order, string $courier, int $attempt): array
    {
        $origin = config('orders.shipping_quotes.origin');
        $store = StoreInfo::getSetting();
        $destination = $order->deliveryPin();
        $weight = (int) config('orders.shipping_quotes.item_weight_grams');
        $phone = fn (?string $number) => preg_replace('/\D+/', '', (string) $number);
        $adminPhone = $phone($origin['contact_phone'] ?: $store->getRawOriginal('phone_number'));

        return [
            'shipper_contact_name' => $origin['contact_name'] ?: $store->store_name,
            'shipper_contact_phone' => $adminPhone,
            'origin_contact_name' => $origin['contact_name'] ?: $store->store_name,
            'origin_contact_phone' => $adminPhone,
            'origin_address' => mb_substr((string) ($origin['address'] ?: $store->address), 0, 300),
            'origin_postal_code' => (int) $origin['postal_code'],
            'origin_coordinate' => ['latitude' => $origin['latitude'], 'longitude' => $origin['longitude']],
            // ORD-14: the driver can always reach the store, whatever number the courier app shows.
            'origin_note' => mb_substr('Pesanan '.$order->code.'.'.($adminPhone !== '' ? ' Ada kendala? WhatsApp admin toko '.PhoneNumber::local($adminPhone).'.' : ''), 0, 100),
            'destination_contact_name' => mb_substr((string) $order->customer_name, 0, 100),
            'destination_contact_phone' => $phone($order->customer_phone),
            'destination_address' => mb_substr((string) $order->address, 0, 300),
            'destination_note' => mb_substr(trim((string) $order->customer_note), 0, 100) ?: null,
            'destination_coordinate' => ['latitude' => $destination['latitude'], 'longitude' => $destination['longitude']],
            'courier_company' => $courier,
            'courier_type' => 'instant',
            'delivery_type' => 'now',
            'order_note' => 'Pesanan '.$order->code,
            'reference_id' => $order->code.'-'.$attempt,
            'items' => $order->items->map(fn (OnlineOrderItem $item) => [
                'name' => mb_substr($item->label(), 0, 80),
                'value' => (int) round((float) $item->unit_price),
                'quantity' => $item->quantity,
                'weight' => $weight,
            ])->values()->all(),
        ];
    }
}
