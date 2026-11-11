<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * ORD-13: the two Biteship calls checkout needs (area search and courier rates). Failures surface as one
 * RuntimeException code and never carry the key, the request or the response body.
 */
class BiteshipClient
{
    /** Biteship's answer when no courier serves the requested point. */
    private const NO_COURIER = 40001010;

    public function configured(): bool
    {
        return str_starts_with((string) config('services.biteship.key'), 'biteship_')
            && str_starts_with((string) config('services.biteship.base_url'), 'https://');
    }

    /** @return list<array{id: string, label: string}> */
    public function areas(string $input): array
    {
        $payload = $this->call('get', '/v1/maps/areas', ['countries' => 'ID', 'input' => $input, 'type' => 'single']);
        $areas = [];
        foreach (array_slice(is_array($payload['areas'] ?? null) ? $payload['areas'] : [], 0, 8) as $area) {
            if (is_string($area['id'] ?? null) && preg_match('/^[A-Za-z0-9]{1,64}$/', $area['id']) && is_string($area['name'] ?? null)) {
                $areas[] = ['id' => $area['id'], 'label' => mb_substr(trim($area['name']), 0, 160)];
            }
        }

        return $areas;
    }

    /**
     * Priced services for one shipment; an empty list when no courier reaches the destination.
     *
     * @return list<array{courier: string, service: string, price: int, duration: ?string, unit: ?string}>
     */
    public function rates(array $body): array
    {
        $payload = $this->call('post', '/v1/rates/couriers', $body);
        $rates = [];
        foreach (is_array($payload['pricing'] ?? null) ? $payload['pricing'] : [] as $rate) {
            $price = $rate['price'] ?? null;
            if (! is_string($rate['courier_code'] ?? null) || ! is_string($rate['courier_service_code'] ?? null)
                || ! is_int($price) || $price <= 0 || $price > 10_000_000) {
                continue;
            }
            $rates[] = [
                'courier' => $rate['courier_code'],
                'service' => $rate['courier_service_code'],
                'price' => $price,
                'duration' => is_string($rate['shipment_duration_range'] ?? null) ? mb_substr($rate['shipment_duration_range'], 0, 20) : null,
                'unit' => is_string($rate['shipment_duration_unit'] ?? null) ? $rate['shipment_duration_unit'] : null,
            ];
        }

        return $rates;
    }

    private function call(string $method, string $path, array $data): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('biteship_not_configured');
        }

        try {
            $request = Http::acceptJson()
                ->withHeaders(['Authorization' => (string) config('services.biteship.key')])
                ->connectTimeout(3)->timeout((int) config('services.biteship.timeout', 8))
                ->withOptions(['allow_redirects' => false]);
            $url = rtrim((string) config('services.biteship.base_url'), '/').$path;
            $response = $method === 'get' ? $request->get($url, $data) : $request->asJson()->post($url, $data);
            $payload = strlen($response->body()) <= 512 * 1024 ? $response->json() : null;
        } catch (Throwable) {
            throw new RuntimeException('biteship_unavailable');
        }

        if ($response->status() === 400 && is_array($payload) && ($payload['code'] ?? null) === self::NO_COURIER) {
            return ['pricing' => []];
        }
        if (! $response->successful() || ! is_array($payload) || ($payload['success'] ?? null) !== true) {
            throw new RuntimeException('biteship_unavailable');
        }

        return $payload;
    }
}
