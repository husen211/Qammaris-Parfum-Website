<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ORD-24 (Owner 2026-11-15): staff paste what the customer sent on WhatsApp instead of searching the map: plain
 * coordinates ("-0.8951, 119.8561"), a Google Maps link with coordinates, or a Sharelok short link
 * (maps.app.goo.gl), which is opened once on Google's own hosts to read the coordinates. Only a point inside the
 * Palu service area counts.
 */
final class MapsLink
{
    private const SHORT = '~^https://(?:maps\.app\.goo\.gl|goo\.gl/maps)/[A-Za-z0-9_-]{4,64}(?:\?[^\s]*)?$~';

    private const GOOGLE_HOST = '/(^|\.)(google\.[a-z.]{2,8}|goo\.gl)$/';

    /** @return array{latitude: float, longitude: float}|null */
    public static function point(?string $input): ?array
    {
        $text = trim((string) $input);
        if ($text === '' || strlen($text) > 500) {
            return null;
        }
        $point = self::fromText($text);
        if ($point === null && preg_match(self::SHORT, $text)) {
            // A resolved link is remembered; a failed attempt (Google slow, odd page) is tried again next time.
            $key = 'maps-link:'.hash('sha256', $text);
            $point = Cache::get($key) ?? self::fromText((string) self::resolve($text));
            if ($point) {
                Cache::put($key, $point, now()->addDays(30));
            }
        }

        return $point && self::inArea($point) ? $point : null;
    }

    /** Coordinates typed as text, or inside a Google Maps link (the place itself before the map's centre). */
    public static function fromText(string $text): ?array
    {
        $text = rawurldecode(str_replace('+', ' ', $text));
        $number = '(-?\d{1,3}\.\d{1,10})';
        $patterns = [
            '~^\s*'.$number.'\s*,\s*'.$number.'\s*$~',
            '~!3d'.$number.'!4d'.$number.'~',
            '~[?&](?:q|query|ll|destination|center)=(?:loc:)?\s*'.$number.'\s*,\s*'.$number.'~',
            '~/(?:search|place|dir/[^/]*)/'.$number.'\s*,\s*'.$number.'~',
            '~@'.$number.','.$number.'~',
        ];
        foreach ($patterns as $index => $pattern) {
            // Plain coordinates only as the whole text; inside anything else only from a Google link.
            if ($index > 0 && ! self::isGoogleUrl($text)) {
                return null;
            }
            if (preg_match($pattern, $text, $match) && abs((float) $match[1]) <= 90 && abs((float) $match[2]) <= 180) {
                return ['latitude' => round((float) $match[1], 6), 'longitude' => round((float) $match[2], 6)];
            }
        }

        return null;
    }

    public static function isGoogleUrl(string $text): bool
    {
        return str_starts_with($text, 'https://') && (bool) preg_match(self::GOOGLE_HOST, strtolower((string) parse_url($text, PHP_URL_HOST)));
    }

    /** The link a Sharelok short link leads to, following at most five redirects that stay on Google. */
    private static function resolve(string $url): ?string
    {
        try {
            for ($hop = 0; $hop < 5; $hop++) {
                $response = Http::withOptions(['allow_redirects' => false])->timeout(5)->connectTimeout(3)
                    ->withHeaders(['User-Agent' => 'QammarisPerfumes/1.0 (+https://qammarisparfum.id)'])->get($url);
                $next = $response->header('Location');
                if (! $response->redirect() || $next === '') {
                    return $url;
                }
                $next = str_starts_with($next, '/') ? 'https://'.parse_url($url, PHP_URL_HOST).$next : $next;
                if (! self::isGoogleUrl($next)) {
                    return null;
                }
                if (self::fromText($next)) {
                    return $next;
                }
                $url = $next;
            }
        } catch (Throwable) {
            return null;
        }

        return $url;
    }

    private static function inArea(array $point): bool
    {
        $area = config('orders.shipping_quotes.service_area');

        return $point['latitude'] >= $area['south'] && $point['latitude'] <= $area['north']
            && $point['longitude'] >= $area['west'] && $point['longitude'] <= $area['east'];
    }

    /** Validation for a pasted location: coordinates in Palu, or any Google Maps / Sharelok link. */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $value = trim((string) $value);
            if (self::fromText($value) !== null && self::point($value) === null) {
                $fail('Koordinat harus di Palu dan sekitarnya.');

                return;
            }
            if (self::isGoogleUrl($value)) {
                return;
            }
            if (self::fromText($value) === null) {
                $fail('Tempel link Google Maps / Sharelok, atau koordinat seperti -0.8951, 119.8561.');
            } elseif (self::point($value) === null) {
                $fail('Koordinat harus di Palu dan sekitarnya.');
            }
        };
    }

    /** What is stored: the pin as a Google Maps link when it can be read, otherwise the pasted link as it is. */
    public static function normalise(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $point = self::point($value);

        return $point ? self::url($point) : $value;
    }

    /** The stored form of a pin, the same as checkout and order-link pins. */
    public static function url(array $point): string
    {
        return sprintf('https://www.google.com/maps?q=%.6F,%.6F', $point['latitude'], $point['longitude']);
    }
}
