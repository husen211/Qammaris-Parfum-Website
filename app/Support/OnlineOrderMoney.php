<?php

namespace App\Support;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderPayment;

/**
 * Money state of an order, derived from the append-only ledger and the Super Admin refund decision.
 *
 * refund_status:
 *   null                  no refund question
 *   needs_reconciliation  history incomplete (e.g. ORD-01 cancel after payment); nothing is assumed
 *   not_required          decided: nothing to return
 *   pending / partial     decided amount not (fully) returned yet → payment_status refund_pending
 *   refunded              decided amount fully returned → payment_status refunded
 */
final class OnlineOrderMoney
{
    public const REFUND_STATUSES = ['needs_reconciliation', 'not_required', 'pending', 'partial', 'refunded'];

    /** @return array{received: int, refunded: int} whole-cent totals with reversed entries netted out */
    public static function totals(OnlineOrder $order): array
    {
        $totals = ['received' => 0, 'refunded' => 0];
        if (! $order->exists) {
            return $totals;
        }
        foreach (OnlineOrderPayment::where('online_order_id', $order->id)->get(['type', 'amount', 'basis']) as $entry) {
            $key = $entry->type === OnlineOrderPayment::TYPE_REFUND ? 'refunded' : 'received';
            $totals[$key] += ($entry->basis === 'reversal' ? -1 : 1) * Rupiah::minorUnits($entry->amount);
        }

        return $totals;
    }

    /** Refund state from the decision and the ledger. Without a decision the stored value is kept as it is. */
    public static function refundStatus(OnlineOrder $order, int $refunded): ?string
    {
        if ($order->refund_due_amount === null) {
            return $order->refund_status === 'needs_reconciliation' ? 'needs_reconciliation' : null;
        }
        $due = Rupiah::minorUnits($order->refund_due_amount);

        return match (true) {
            $due === 0 => 'not_required',
            $refunded === 0 => 'pending',
            $refunded < $due => 'partial',
            default => 'refunded',
        };
    }

    /** Writes refund_status and the refund part of payment_status. `$basePayment` is paid/unpaid before refunds. */
    public static function apply(OnlineOrder $order, string $basePayment): void
    {
        $status = self::refundStatus($order, self::totals($order)['refunded']);
        $order->refund_status = $status;
        $order->payment_status = match ($status) {
            'pending', 'partial' => 'refund_pending',
            'refunded' => 'refunded',
            default => $basePayment,
        };
    }

    /** Payment state for V2 orders: paid once the ledger covers the customer total. */
    public static function basePaymentFromLedger(OnlineOrder $order): string
    {
        $received = self::totals($order)['received'];

        return $received > 0 && $received >= Rupiah::minorUnits($order->customerTotal()) ? 'paid' : 'unpaid';
    }
}
