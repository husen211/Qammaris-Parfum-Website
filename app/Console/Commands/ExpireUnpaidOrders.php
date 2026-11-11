<?php

namespace App\Console\Commands;

use App\Actions\Orders\OnlineOrderFulfillment;
use App\Models\OnlineOrder;
use App\Support\OnlineOrderMoney;
use Illuminate\Console\Command;
use Throwable;

/**
 * ORD-15 (Owner 2026-11-11): an order that can be paid but receives no money within 48 hours is cancelled.
 * The clock starts when the order can first be paid (final total; confirmed, or a website order payable at once);
 * this sweep sets that deadline, clears it when the order stops being payable, and cancels what is overdue.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'qammaris:orders:expire-unpaid {--limit=200}';

    protected $description = 'Set payment deadlines and cancel orders left unpaid past them';

    public function handle(OnlineOrderFulfillment $fulfillment): int
    {
        $hours = (int) config('orders.unpaid_cancel_hours', 48);
        if ($hours <= 0) {
            $this->info('Automatic cancellation is off.');

            return self::SUCCESS;
        }
        $set = $cleared = $cancelled = 0;
        $orders = OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)
            ->whereIn('lifecycle', ['draft', 'active'])->where('payment_status', 'unpaid')
            ->with('items')->limit((int) $this->option('limit'))->get();

        foreach ($orders as $order) {
            $payable = self::expirable($order);
            if (! $payable) {
                if ($order->payment_due_at !== null) {
                    OnlineOrder::query()->whereKey($order->getKey())->update(['payment_due_at' => null]);
                    $cleared++;
                }

                continue;
            }
            if ($order->payment_due_at === null) {
                // Saved without a revision: a deadline is not a change staff or the App act on.
                OnlineOrder::query()->whereKey($order->getKey())->update(['payment_due_at' => now()->addHours($hours)]);
                $set++;

                continue;
            }
            if ($order->payment_due_at->isPast()) {
                try {
                    if ($fulfillment->expireUnpaid($order, $hours)->lifecycle === 'cancelled') {
                        $cancelled++;
                    }
                } catch (Throwable $failure) {
                    $this->warn($order->code.': '.$failure->getMessage());
                }
            }
        }
        $this->info("Deadlines set {$set}, cleared {$cleared}, cancelled {$cancelled}.");

        return self::SUCCESS;
    }

    /** Can be paid now, has received nothing, is not kept, and nothing has left the store. */
    public static function expirable(OnlineOrder $order): bool
    {
        return $order->customerCanPay() && $order->keep_status !== 'active' && $order->handover_status === 'pending'
            && ! $order->biteshipBusy() && OnlineOrderMoney::totals($order)['received'] === 0;
    }
}
