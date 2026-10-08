<?php

namespace App\Support;

use Closure;
use DateTimeInterface;

/**
 * Transitional one-way mapping (ORD-02c slice 1): ORD-01 `stage` → separate ORD-02 state dimensions.
 * Used by the backfill migration and by the OnlineOrder saving hook while ORD-01 screens still drive `stage`.
 * ORD-02d reverses the authority (dimensions first) and removes the hook; never sync both ways at once.
 */
final class OnlineOrderLegacyState
{
    private const RANK = ['awaiting_customer' => 0, 'details_received' => 1, 'paid' => 2, 'shipped' => 3, 'completed' => 4];

    /**
     * @param  array<string, mixed>  $order  current attributes; `previous_stage` is the stage before a cancel when known
     * @param  Closure(string): DateTimeInterface  $reachedAt  when the order reached an ORD-01 stage
     * @return array<string, mixed> state columns to write
     */
    public static function dimensions(array $order, Closure $reachedAt): array
    {
        $stage = $order['stage'];
        if ($stage === 'cancelled') {
            $base = isset($order['previous_stage'], self::RANK[$order['previous_stage']])
                ? self::dimensions(['stage' => $order['previous_stage']] + $order, $reachedAt)
                : [];
            $payment = $base['payment_status'] ?? $order['payment_status'] ?? 'unpaid';

            // Owner D4: cancelling after payment leaves a refund obligation that must stay visible.
            return ['lifecycle' => 'cancelled', 'payment_status' => in_array($payment, ['paid', 'refund_pending'], true) ? 'refund_pending' : $payment] + $base;
        }

        $rank = self::RANK[$stage];
        $type = $order['fulfillment'] ?? null;
        $courier = $order['courier'] ?? null;
        $paid = $rank >= self::RANK['paid'];
        $handedOver = $rank >= self::RANK['shipped'];
        $isJnt = $courier === 'jnt' || ($type === 'intercity' && $courier === null);
        $local = $type === 'local_delivery' && ! $isJnt;

        return [
            'lifecycle' => match ($stage) {
                'awaiting_customer' => 'awaiting_customer',
                'completed' => 'completed',
                default => 'active',
            },
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'payment_confirmed_at' => $paid ? ($order['payment_confirmed_at'] ?? $reachedAt('paid')) : null,
            'preparation_status' => $handedOver ? 'packed' : 'not_started',
            'courier_booking_responsibility' => $local ? 'store' : null,
            'courier_provider' => $local ? $courier : null,
            'courier_status' => $local ? ($handedOver ? 'arrived' : 'unassigned') : 'not_needed',
            'jnt_status' => $isJnt && $type !== 'pickup' ? ($handedOver ? 'picked_up' : 'not_requested') : null,
            'jnt_picked_up_at' => $isJnt && $handedOver && $type !== 'pickup' ? ($order['jnt_picked_up_at'] ?? $reachedAt('shipped')) : null,
            'handover_status' => $handedOver ? 'handed_over' : 'pending',
            'handed_to' => $handedOver ? ($type === 'pickup' ? 'customer' : ($isJnt ? 'jnt' : 'courier')) : null,
            'handed_over_at' => $handedOver ? ($order['handed_over_at'] ?? $reachedAt($type === 'pickup' ? 'completed' : 'shipped')) : null,
            'delivery_status' => $stage === 'completed' && $type !== 'pickup' ? 'delivered' : 'unconfirmed',
            'delivered_at' => $stage === 'completed' && $type !== 'pickup' ? ($order['delivered_at'] ?? $reachedAt('completed')) : null,
        ];
    }
}
