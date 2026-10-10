<?php

namespace App\Support;

use App\Models\OnlineOrder;
use Illuminate\Support\Collection;

/**
 * ORD-04 "Pesanan Saya" without login: the order links this browser session opened (checkout or status link).
 * Only the server-side session holds the raw tokens; each detail still opens through its token (customer link rules).
 * The list lasts as long as the session; it is not an account.
 */
final class CustomerOrderHistory
{
    private const KEY = 'customer_orders';

    private const LIMIT = 20;

    public static function remember(OnlineOrder $order, string $token): void
    {
        $list = array_filter((array) session(self::KEY, []), fn ($value) => is_string($value) && $value !== $token);
        array_unshift($list, $token);
        session([self::KEY => array_slice(array_values($list), 0, self::LIMIT)]);
    }

    /** @return Collection<int, array{order: OnlineOrder, token: string}> newest first; dead links are dropped */
    public static function entries(): Collection
    {
        $tokens = array_values(array_filter((array) session(self::KEY, []), fn ($value) => is_string($value) && strlen($value) === 40));
        $orders = OnlineOrder::query()->with('items')->whereIn('customer_token_hash', array_map([OnlineOrder::class, 'tokenHash'], $tokens))
            ->get()->keyBy('customer_token_hash');
        $entries = collect($tokens)
            ->map(fn (string $token) => ($order = $orders->get(OnlineOrder::tokenHash($token))) && $order->customerLinkUsable() ? ['order' => $order, 'token' => $token] : null)
            ->filter()->values();
        session([self::KEY => $entries->pluck('token')->all()]);

        return $entries;
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }
}
