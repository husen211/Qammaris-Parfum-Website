<?php

namespace App\Support;

use App\Models\OnlineOrder;

/**
 * The one Website rule set for derived order state (contract r4.1 §7 and §9). The App shows `queue`
 * and never recomputes it.
 */
final class OnlineOrderState
{
    public const QUEUES = ['needs_handling', 'preparing', 'ready', 'awaiting_pickup', 'in_delivery', 'done', 'has_issue'];

    /** Ordered rules; the first match wins. Null = not shown in the App queue. */
    public static function queue(OnlineOrder $order, int $openIssues = 0, bool $preparationClaimed = false): ?string
    {
        if (in_array($order->lifecycle, ['draft', 'awaiting_customer', 'cancelled'], true)) {
            return null;
        }
        if ($openIssues > 0) {
            return 'has_issue';
        }
        if ($order->lifecycle === 'completed') {
            return 'done';
        }
        if ($order->handover_status === 'handed_over') {
            return $order->fulfillment === 'pickup' || $order->delivery_status === 'delivered' ? 'done' : 'in_delivery';
        }
        if ($order->preparation_status === 'packed') {
            $pickupComing = in_array($order->courier_status, ['requested', 'arrived'], true)
                || in_array($order->jnt_status, ['pickup_requested', 'qr_available'], true);

            return $pickupComing ? 'awaiting_pickup' : 'ready';
        }
        if ($order->preparation_status === 'preparing' || $preparationClaimed) {
            return 'preparing';
        }

        return 'needs_handling';
    }

    /** @return array{unpaid: bool, open_refund: bool, open_reimbursement: bool} badges; they never change the queue */
    public static function flags(OnlineOrder $order): array
    {
        return [
            'unpaid' => $order->payment_status === 'unpaid',
            'open_refund' => $order->payment_status === 'refund_pending',
            'open_reimbursement' => $order->needsReimbursement(),
        ];
    }

    /**
     * `completed` needs handover, payment and no open issue or refund. An unpaid staff reimbursement
     * does not block it (Owner R8); it stays an open obligation shown on the order.
     */
    public static function canComplete(OnlineOrder $order, int $openIssues = 0): bool
    {
        return $order->lifecycle === 'active'
            && $order->handover_status === 'handed_over'
            && $order->payment_status === 'paid'
            && $openIssues === 0;
    }
}
