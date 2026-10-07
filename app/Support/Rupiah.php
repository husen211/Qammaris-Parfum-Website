<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class Rupiah
{
    public const WHOLE_PRICE_PATTERN = '/^\d+(?:\.0{1,2})?$/D';

    /** Convert decimal rupiah to integer hundredths without rounding. */
    public static function minorUnits(int|float|string $amount): int
    {
        $value = is_float($amount) ? json_encode($amount, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION) : (string) $amount;
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $value, $parts)) {
            throw new InvalidArgumentException('Rupiah must have at most two decimal places.');
        }

        $digits = ltrim($parts[2].str_pad($parts[3] ?? '', 2, '0'), '0') ?: '0';
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new OverflowException('Rupiah exceeds the integer calculation range.');
        }

        return ($parts[1] === '-' ? -1 : 1) * (int) $digits;
    }

    public static function decimal(int $minorUnits): string
    {
        if ($minorUnits === PHP_INT_MIN) {
            throw new OverflowException('Rupiah exceeds the integer calculation range.');
        }
        $absolute = abs($minorUnits);

        return ($minorUnits < 0 ? '-' : '').intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function sum(array $amounts): string
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += self::minorUnits($amount);
            if (! is_int($total) || $total === PHP_INT_MIN) {
                throw new OverflowException('Rupiah total exceeds the integer calculation range.');
            }
        }

        return self::decimal($total);
    }

    public static function format(int|float|string $amount): string
    {
        $minorUnits = self::minorUnits($amount);
        [$whole, $fraction] = explode('.', self::decimal($minorUnits));
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', ltrim($whole, '-'));

        // Existing fractional records remain truthful until explicitly corrected; whole prices have no decimals.
        return 'Rp '.($minorUnits < 0 ? '-' : '').$grouped.($fraction === '00' ? '' : ','.$fraction);
    }
}
