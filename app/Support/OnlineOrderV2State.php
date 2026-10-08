<?php

namespace App\Support;

use App\Models\OnlineOrder;

/**
 * State rules for V2 orders (ORD-02c slice 3). Dimensions are the authority; `stage` is derived only so a
 * code rollback to ORD-01 can still read the order.
 */
final class OnlineOrderV2State
{
    /** Saving hook for V2 rows: defaults per fulfillment, details arrival, derived stage. */
    public static function normalize(OnlineOrder $order): void
    {
        $order->lifecycle ??= 'awaiting_customer';
        // Shared customer/admin detail code marks arrival by setting stage; for V2 that activates the order.
        if ($order->lifecycle === 'awaiting_customer' && $order->isDirty('stage') && $order->stage === OnlineOrder::STAGE_DETAILS_RECEIVED) {
            $order->lifecycle = 'active';
        }
        $order->payment_status ??= 'unpaid';
        $order->preparation_status ??= 'not_started';
        $order->handover_status ??= 'pending';
        $order->delivery_status ??= 'unconfirmed';

        if ($order->handover_status === 'pending') {
            if ($order->fulfillment === 'local_delivery') {
                $order->courier_booking_responsibility ??= 'store';
                if (in_array($order->courier_status, [null, 'not_needed'], true) && $order->courier_booking_responsibility === 'store') {
                    $order->courier_status = 'unassigned';
                }
                $order->jnt_status = null;
            } else {
                $order->courier_booking_responsibility = null;
                $order->courier_provider = null;
                $order->courier_status = 'not_needed';
                $order->jnt_status = $order->fulfillment === 'intercity' ? ($order->jnt_status ?? 'not_requested') : null;
            }
        }
        $order->stage = self::stage($order);
    }

    public static function stage(OnlineOrder $order): string
    {
        return match (true) {
            $order->lifecycle === 'cancelled' => OnlineOrder::STAGE_CANCELLED,
            $order->lifecycle === 'completed' => OnlineOrder::STAGE_COMPLETED,
            in_array($order->lifecycle, ['draft', 'awaiting_customer'], true) => OnlineOrder::STAGE_AWAITING_CUSTOMER,
            $order->handover_status === 'handed_over' && $order->fulfillment !== 'pickup' => OnlineOrder::STAGE_SHIPPED,
            $order->payment_status !== 'unpaid' => OnlineOrder::STAGE_PAID,
            default => OnlineOrder::STAGE_DETAILS_RECEIVED,
        };
    }

    /** Completes the order once handed over, paid, without open issues or open refund (contract §7, Owner R8). */
    public static function settle(OnlineOrder $order): bool
    {
        $openIssues = $order->exists ? $order->issues()->where('status', 'open')->count() : 0;
        if (! OnlineOrderState::canComplete($order, $openIssues)) {
            return false;
        }
        $order->lifecycle = 'completed';
        $order->closed_at = now();

        return true;
    }
}
