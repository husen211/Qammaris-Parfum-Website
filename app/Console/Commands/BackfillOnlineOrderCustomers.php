<?php

namespace App\Console\Commands;

use App\Actions\Orders\RememberCustomer;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ORD-05: links existing V2 orders to customers the same way new orders are linked (same WhatsApp number = same
 * customer) and keeps the addresses of handed-over orders. Preview by default: counts only, no names or numbers,
 * nothing written. --apply writes in one transaction per order without a new revision, so no webhook is sent.
 * Website drafts that were never confirmed are skipped, as for new orders.
 */
class BackfillOnlineOrderCustomers extends Command
{
    protected $signature = 'qammaris:customers:backfill {--apply : Write the links; without it only the preview is shown}';

    protected $description = 'Link existing online orders to saved customers (preview unless --apply)';

    public function handle(RememberCustomer $remember): int
    {
        $orders = OnlineOrder::query()->where('state_model', OnlineOrder::STATE_V2)->whereNull('customer_id')
            ->where('lifecycle', '!=', 'draft')->orderBy('id')->get(['id', 'customer_name', 'customer_phone']);
        $known = Customer::whereNull('anonymized_at')->pluck('phone')->flip();
        $plan = ['link_existing' => 0, 'new_customers' => 0, 'link_new' => 0, 'skipped' => 0];
        foreach ($orders as $order) {
            $phone = PhoneNumber::normalize($order->customer_phone);
            if ($phone === null || blank($order->customer_name)) {
                $plan['skipped']++;
            } elseif ($known->has($phone)) {
                $plan[$known[$phone] === 'new' ? 'link_new' : 'link_existing']++;
            } else {
                $known[$phone] = 'new';
                $plan['new_customers']++;
                $plan['link_new']++;
            }
        }
        $this->table(['Orders without customer', 'Link to existing', 'New customers', 'Link to new', 'Skipped (no name/number)'],
            [[$orders->count(), $plan['link_existing'], $plan['new_customers'], $plan['link_new'], $plan['skipped']]]);

        if (! $this->option('apply')) {
            $this->info('Preview only. Run again with --apply to write.');

            return self::SUCCESS;
        }

        $linked = 0;
        $addresses = 0;
        foreach ($orders as $candidate) {
            DB::transaction(function () use ($candidate, $remember, &$linked, &$addresses): void {
                $order = OnlineOrder::whereKey($candidate->id)->lockForUpdate()->first();
                if ($order === null || ! $remember->link($order, null, 'admin')) {
                    return;
                }
                $linked++;
                if ($order->handover_status === 'handed_over') {
                    $remember->rememberAddress($order);
                    $addresses += $order->customer_address_id !== null ? 1 : 0;
                }
                $order->save();
            });
        }
        $this->info("Linked {$linked} order(s); kept {$addresses} address(es).");

        return self::SUCCESS;
    }
}
