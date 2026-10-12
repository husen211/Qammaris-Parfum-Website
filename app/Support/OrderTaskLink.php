<?php

namespace App\Support;

use App\Models\OnlineOrder;
use App\Models\StoreInfo;

/**
 * ORD-04: the order link in the staff WhatsApp group. With App task links on, it is the App's own order URL
 * ({app orders url}/{public id}) so an installed PWA can capture it; login and browser fallback are the App's.
 * A URL that belongs to the other environment is refused (test environments never link to the production App,
 * production never to a temporary tunnel); then the Website task route is used, which redirects as before.
 */
final class OrderTaskLink
{
    public const PRODUCTION_HOSTS = ['qammarisapp.com', 'www.qammarisapp.com', 'app.qammarisapp.com'];

    /** @return array{url: string, in_app: bool} */
    public static function for(OnlineOrder $order): array
    {
        $base = rtrim((string) config('orders_api.app_orders_url'), '/');
        if (config('orders_api.app_task_links') && self::fitsEnvironment($base)) {
            return ['url' => $base.'/'.$order->public_id, 'in_app' => true];
        }

        return ['url' => route('admin.orders.task', $order->public_id), 'in_app' => false];
    }

    private static function fitsEnvironment(string $base): bool
    {
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        if ($host === '' || parse_url($base, PHP_URL_SCHEME) !== 'https') {
            return false;
        }
        $production = in_array($host, self::PRODUCTION_HOSTS, true);

        return StoreInfo::usesTestWhatsapp() ? ! $production : ! str_ends_with($host, '.trycloudflare.com');
    }
}
