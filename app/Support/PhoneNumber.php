<?php

namespace App\Support;

final class PhoneNumber
{
    /** Indonesian WhatsApp number as digits with country code (0812… → 62812…), or null when implausible. */
    public static function normalize(?string $number): ?string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);
        if ($normalized === '') {
            return null;
        }
        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return strlen($normalized) >= 8 && strlen($normalized) <= 15 ? $normalized : null;
    }

    /** The way people type it: 62812… or +62 812… becomes 0812…; anything else is returned as given. */
    public static function local(?string $number): string
    {
        $normalized = self::normalize($number);

        return $normalized === null ? (string) $number : (str_starts_with($normalized, '62') ? '0'.substr($normalized, 2) : $normalized);
    }
}
