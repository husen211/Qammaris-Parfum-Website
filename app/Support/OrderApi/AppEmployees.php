<?php

namespace App\Support\OrderApi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ORD-08 (Owner 2026-10-17): the "Uang siapa" employees are the active Qammaris App employees themselves (ID + name),
 * read from the App with the same signed request as the order webhook. Cached briefly; when the App is unreachable the
 * last known list is used and marked stale, so staff can still book a courier. The order page reads it fresh
 * (`refresh: true`) so a rename or a new employee in the App shows at once.
 */
final class AppEmployees
{
    private const FRESH_KEY = 'orders.app_employees.fresh';

    private const LAST_KEY = 'orders.app_employees.last';

    private const FRESH_SECONDS = 300;

    /** @return array{employees: array<string, string>, stale: bool} app_user_id => display_name */
    public static function list(bool $refresh = false): array
    {
        $fresh = $refresh ? null : Cache::get(self::FRESH_KEY);
        if (is_array($fresh)) {
            return ['employees' => $fresh, 'stale' => false];
        }
        $employees = self::fetch();
        if ($employees !== null) {
            Cache::put(self::FRESH_KEY, $employees, self::FRESH_SECONDS);
            Cache::forever(self::LAST_KEY, $employees);

            return ['employees' => $employees, 'stale' => false];
        }

        return ['employees' => (array) Cache::get(self::LAST_KEY, []), 'stale' => true];
    }

    /** Current App name for an account ID (rename-proof display), or the given fallback. */
    public static function nameOf(?string $appUserId, ?string $fallback): ?string
    {
        if ($appUserId === null) {
            return $fallback;
        }

        return ((array) Cache::get(self::LAST_KEY, []))[$appUserId] ?? $fallback;
    }

    public static function url(): ?string
    {
        $configured = (string) config('orders_api.app_employees_url');
        if ($configured !== '') {
            return $configured;
        }
        $webhook = (string) config('orders_api.webhook_url');

        return $webhook !== '' && str_ends_with($webhook, '/events') ? substr($webhook, 0, -strlen('/events')).'/employees' : null;
    }

    /** @return array<string, string>|null null when the App could not be read */
    private static function fetch(): ?array
    {
        $url = self::url();
        $secret = (string) config('orders_api.webhook_secret');
        if ($url === null || $secret === '') {
            return null;
        }
        $path = (string) parse_url($url, PHP_URL_PATH).(($query = parse_url($url, PHP_URL_QUERY)) ? '?'.$query : '');
        $timestamp = now()->timestamp;
        try {
            $response = Http::timeout(5)->acceptJson()->withHeaders([
                'X-Qammaris-Client' => (string) config('orders_api.webhook_client_id'),
                'X-Qammaris-Timestamp' => (string) $timestamp,
                'X-Qammaris-Signature' => OrderApiSignature::sign($secret, $timestamp, 'GET', $path, ''),
                'X-Qammaris-Api-Version' => '1',
            ])->get($url);
        } catch (Throwable $error) {
            Log::warning('orders: Qammaris App employees unreachable', ['error' => class_basename($error)]);

            return null;
        }
        if (! $response->successful() || ! is_array($rows = $response->json('data'))) {
            Log::warning('orders: Qammaris App employees refused', ['status' => $response->status()]);

            return null;
        }
        $employees = [];
        foreach ($rows as $row) {
            $id = is_array($row) ? (string) ($row['app_user_id'] ?? '') : '';
            $name = is_array($row) ? trim((string) ($row['display_name'] ?? '')) : '';
            if (preg_match('/^[a-f0-9]{24}$/', $id) && $name !== '' && mb_strlen($name) <= 60) {
                $employees[$id] = $name;
            }
        }

        return $employees;
    }
}
