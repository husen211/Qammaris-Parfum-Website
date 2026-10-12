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
}
