<?php

namespace App\Support;

/** ORD-15: logo files (public/images/partners) for couriers and banks the customer sees by name. */
final class PartnerLogos
{
    private const FILES = ['gosend' => 'gojek.svg', 'grab' => 'grab.svg', 'jnt' => 'jnt.svg', 'bri' => 'bri.svg'];

    public static function file(?string $name): ?string
    {
        return self::FILES[$name] ?? null;
    }

    /** @return array<string, string> provider => public URL, for the checkout script */
    public static function urls(): array
    {
        return array_map(fn (string $file) => asset('images/partners/'.$file), self::FILES);
    }
}
