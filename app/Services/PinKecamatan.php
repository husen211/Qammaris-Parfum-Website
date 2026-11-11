<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ORD-17: the kecamatan an OpenStreetMap reverse lookup (Nominatim) gives for a delivery pin. Only a hint for the
 * customer: OpenStreetMap boundaries around Palu are not always right, so the page never replaces their choice by
 * itself. Coordinates are rounded to ~11 m and the answer is cached, so a pin is looked up at most once.
 */
class PinKecamatan
{
    public function find(float $latitude, float $longitude): ?string
    {
        $key = sprintf('pin-kecamatan:%.4f,%.4f', $latitude, $longitude);

        return Cache::remember($key, now()->addDays(30), function () use ($latitude, $longitude): ?string {
            try {
                $response = Http::withHeaders(['User-Agent' => 'QammarisPerfumes/1.0 (+'.config('app.url').')'])
                    ->acceptJson()->connectTimeout(3)->timeout(5)
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'jsonv2', 'lat' => round($latitude, 4), 'lon' => round($longitude, 4),
                        'zoom' => 14, 'addressdetails' => 1, 'accept-language' => 'id',
                    ]);
            } catch (Throwable) {
                return null;
            }
            $name = $response->successful() ? ($response->json('address.municipality') ?? $response->json('address.city_district')) : null;
            if (! is_string($name) || ! preg_match('/^Kecamatan\s+(.{2,60})$/u', trim($name), $match)) {
                return null;
            }

            return mb_substr(trim($match[1]), 0, 60);
        });
    }
}
