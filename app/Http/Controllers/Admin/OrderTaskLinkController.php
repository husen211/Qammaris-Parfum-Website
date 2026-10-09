<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnlineOrder;
use Illuminate\Http\RedirectResponse;

/**
 * Stable task link in WhatsApp group messages (ORD-02e). Decided when clicked: the Admin PWA order page (login
 * required) until the App integration is live, then the App order page. Never a bearer link; unknown or legacy
 * IDs only lead to the order list, so the link reveals nothing.
 */
class OrderTaskLinkController extends Controller
{
    public function __invoke(string $publicId): RedirectResponse
    {
        $order = preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $publicId)
            ? OnlineOrder::query()->where('public_id', $publicId)->where('state_model', OnlineOrder::STATE_V2)->first()
            : null;
        $target = match (true) {
            $order === null => route('admin.orders.index'),
            (bool) config('orders_api.app_task_links') => rtrim((string) config('orders_api.app_orders_url'), '/').'/'.$order->public_id,
            default => route('admin.orders.show', $order),
        };

        return redirect()->away($target)->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
