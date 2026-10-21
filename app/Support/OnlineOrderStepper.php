<?php

namespace App\Support;

use App\Models\OnlineOrder;

/**
 * ORD-04 operational stepper, derived only from backend state (the same fields API v1 sends to the App):
 * Konfirmasi (draft/awaiting_customer) → Pembayaran (not paid) → Siapkan (not packed) → Pengiriman (not handed over)
 * → Selesai (handed over or completed). Exactly one step is current; the domain refuses steps out of order.
 */
final class OnlineOrderStepper
{
    public const STEPS = [
        'konfirmasi' => 'Konfirmasi pesanan',
        'pembayaran' => 'Pembayaran',
        'persiapan' => 'Siapkan pesanan',
        'pengiriman' => 'Pengiriman',
        'selesai' => 'Selesai',
    ];

    /** Current step key; null for a cancelled order. */
    public static function current(OnlineOrder $order): ?string
    {
        return match (true) {
            $order->lifecycle === 'cancelled' => null,
            in_array($order->lifecycle, ['draft', 'awaiting_customer'], true) => 'konfirmasi',
            $order->lifecycle === 'completed' || $order->handover_status === 'handed_over' => 'selesai',
            $order->payment_status !== 'paid' => 'pembayaran',
            $order->preparation_status !== 'packed' => 'persiapan',
            default => 'pengiriman',
        };
    }

    /** @return list<array{key: string, label: string, state: string}> state: done | current | todo (cancelled: reached steps are done) */
    public static function steps(OnlineOrder $order): array
    {
        $keys = array_keys(self::STEPS);
        $current = self::current($order);
        $index = $current === null ? self::reachedBeforeCancel($order) : array_search($current, $keys, true);
        $finished = $current === 'selesai' && $order->lifecycle === 'completed';

        return array_map(fn (string $key, int $i) => [
            'key' => $key,
            'label' => $key === 'pengiriman' && $order->fulfillment === 'pickup' ? 'Diambil customer' : self::STEPS[$key],
            'state' => match (true) {
                $i < $index, $i === $index && ($finished || $current === null) => 'done',
                $i === $index => 'current',
                default => 'todo',
            },
        ], $keys, array_keys($keys));
    }

    /**
     * Payment can be requested once the total is final: pickup, ongkir paid to the driver (never part of the
     * total, so its amount may come later, ORD-10), or the shipping fee and who pays it are set.
     */
    public static function totalIsFinal(OnlineOrder $order): bool
    {
        return $order->fulfillment === 'pickup' || $order->shipping_payer === 'cash_to_driver'
            || ($order->shipping_fee !== null && $order->shipping_payer !== null);
    }

    private static function reachedBeforeCancel(OnlineOrder $order): int
    {
        return match (true) {
            $order->preparation_status === 'packed' => 2,
            $order->payment_status !== 'unpaid' => 1,
            default => 0,
        };
    }
}
