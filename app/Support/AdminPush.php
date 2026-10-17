<?php

namespace App\Support;

use App\Jobs\SendAdminPush;
use App\Models\OnlineOrder;

/**
 * ORD-09 (Owner 2026-10-17): push notification to the order team (Super Admin + Staff Order) when a customer orders
 * on the website. The message holds no phone number or address: it shows on lock screens of shared devices.
 */
final class AdminPush
{
    public static function enabled(): bool
    {
        return config('admin_push.vapid.public_key') !== '' && config('admin_push.vapid.private_key') !== '';
    }

    public static function newWebsiteOrder(OnlineOrder $order): void
    {
        if (! self::enabled()) {
            return;
        }
        $order->loadMissing('items');
        $count = (int) $order->items->sum('quantity');
        SendAdminPush::dispatch([
            'title' => 'Pesanan baru dari website',
            'body' => $order->code.' · '.$count.' barang · '.Rupiah::format($order->customerTotal()).' · menunggu konfirmasi',
            'url' => route('admin.orders.show', $order, false),
            'tag' => 'order-'.$order->id,
        ])->afterCommit();
    }
}
