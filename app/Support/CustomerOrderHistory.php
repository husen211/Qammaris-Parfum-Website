<?php

namespace App\Support;

use App\Models\GuestOrderHistory;
use App\Models\OnlineOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * ORD-04 "Pesanan Saya" without login, kept 10 days on this browser only.
 *
 * The browser gets a random key in an encrypted, HttpOnly, Secure (when HTTPS), SameSite=Lax cookie; the server keeps
 * only its SHA-256 and which orders that browser opened (checkout or status link). No order token, recipient data or
 * account lives in the cookie or in localStorage. The Laravel session lifetime is untouched (admin policy differs).
 * Each detail still opens through its own link and follows the customer-link rules.
 */
final class CustomerOrderHistory
{
    public const COOKIE = 'qam_orders';

    public const DAYS = 10;

    public const LIMIT = 20;

    public static function remember(OnlineOrder $order): void
    {
        $history = self::current() ?? self::start();
        $history->entries()->updateOrCreate(['online_order_id' => $order->id], ['opened_at' => now()]);
        $keep = $history->entries()->orderByDesc('opened_at')->orderByDesc('id')->limit(self::LIMIT)->pluck('id');
        $history->entries()->whereNotIn('id', $keep)->delete();
        $history->forceFill(['expires_at' => now()->addDays(self::DAYS)])->save();
    }

    /** @return Collection<int, array{order: OnlineOrder, token: string}> newest first; expired or dead links are left out */
    public static function entries(): Collection
    {
        $history = self::current();
        if (! $history) {
            return collect();
        }

        return $history->entries()->with('order.items')->where('opened_at', '>=', now()->subDays(self::DAYS))
            ->orderByDesc('opened_at')->orderByDesc('id')->limit(self::LIMIT)->get()
            ->filter(fn ($entry) => $entry->order && $entry->order->customerLinkUsable())
            ->map(fn ($entry) => ['order' => $entry->order, 'token' => $entry->order->customer_token_encrypted])
            ->values();
    }

    public static function forget(): void
    {
        self::current()?->delete();
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    private static function current(): ?GuestOrderHistory
    {
        $key = request()->cookie(self::COOKIE);
        if (! is_string($key) || strlen($key) !== 64) {
            return null;
        }

        return GuestOrderHistory::query()->where('key_hash', hash('sha256', $key))->where('expires_at', '>', now())->first();
    }

    private static function start(): GuestOrderHistory
    {
        // Lazy housekeeping: expired lists are removed whenever a new one starts.
        GuestOrderHistory::query()->where('expires_at', '<', now())->limit(200)->delete();
        $key = Str::random(64);
        Cookie::queue(Cookie::make(self::COOKIE, $key, self::DAYS * 24 * 60, '/', null, request()->isSecure() || config('session.secure'), true, false, 'lax'));
        // The same request may remember again; let current() see the new key.
        request()->cookies->set(self::COOKIE, $key);

        return GuestOrderHistory::create(['key_hash' => hash('sha256', $key), 'expires_at' => now()->addDays(self::DAYS)]);
    }
}
