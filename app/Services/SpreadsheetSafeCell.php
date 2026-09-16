<?php

namespace App\Services;

class SpreadsheetSafeCell
{
    public function sanitize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $trimmed = ltrim($value);

        if ($value !== '' && (
            in_array($value[0], ["\t", "\r", "\n"], true)
            || preg_match('/^[=+\-@]/u', $trimmed) === 1
        )) {
            return "'".$value;
        }

        return $value;
    }
}
