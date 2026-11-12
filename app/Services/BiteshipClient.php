<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * ORD-13: the two Biteship calls checkout needs (area search and courier rates); ORD-14: booking, reading and
 * cancelling an instant courier. Failures surface as a RuntimeException code (or Biteship's short refusal for a
 * booking) and never carry the key, the request or the response body.
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
        $payload = $this->call('get', '/v1/maps/areas', ['countries' => 'ID', 'input' => $input, 'type' => 'single'], retry: true);
        $areas = [];
        foreach (array_slice(is_array($payload['areas'] ?? null) ? $payload['areas'] : [], 0, 20) as $area) {
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
        $payload = $this->call('post', '/v1/rates/couriers', $body, retry: true);
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

    /**
     * ORD-14: book a courier. Returns the order as normalised by order(); a refusal throws with Biteship's own
     * message (e.g. balance or account), never the key or the request.
     */
    public function createOrder(array $body): array
    {
        return $this->normalise($this->call('post', '/v1/orders', $body, keepError: true));
    }

    /** @return array{id: string, status: string, price: ?int, company: ?string, link: ?string, driver_name: ?string, driver_phone: ?string, driver_plate: ?string, waybill: ?string} */
    public function order(string $id): array
    {
        return $this->normalise($this->call('get', '/v1/orders/'.rawurlencode($id), []));
    }

    public function cancelOrder(string $id, string $reason): array
    {
        // The cancel answer may leave out the courier block; the id is the one that was cancelled.
        return $this->normalise(['id' => $id] + $this->call('post', '/v1/orders/'.rawurlencode($id).'/cancel',
            ['cancellation_reason_code' => 'others', 'cancellation_reason' => mb_substr($reason, 0, 200)], keepError: true));
    }

    /** Biteship writes statuses as "picking_up" or "pickingUp"; both become picking_up. */
    public static function status(?string $status): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', trim((string) $status)));
    }

    private function normalise(array $order): array
    {
        $courier = is_array($order['courier'] ?? null) ? $order['courier'] : [];
        $text = fn ($value, int $max = 160) => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
        if (! is_string($order['id'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $order['id'])) {
            throw new RuntimeException('biteship_invalid_order');
        }

        $status = self::status($order['status'] ?? null);
        $driver = $text($courier['driver_name'] ?? $courier['name'] ?? null, 80);
        // Live 2026-11-12: Biteship kept the order "confirmed" after the driver was assigned and on the way (its
        // tracking page already said so). A driver name means the driver is found, whatever the order status says.
        if (in_array($status, ['confirmed', 'scheduled'], true) && $driver !== null) {
            $status = 'allocated';
        }

        return [
            'id' => $order['id'],
            'status' => $status,
            'price' => is_numeric($order['price'] ?? null) ? (int) $order['price'] : null,
            'company' => $text($courier['company'] ?? null, 40),
            'link' => is_string($courier['link'] ?? null) && str_starts_with($courier['link'], 'https://') ? mb_substr($courier['link'], 0, 500) : null,
            'driver_name' => $driver,
            'driver_phone' => $text($courier['driver_phone'] ?? $courier['phone'] ?? null, 30),
            'driver_plate' => $text($courier['driver_plate_number'] ?? null, 20),
            'waybill' => $text($courier['waybill_id'] ?? null, 60),
        ];
    }

    /** ORD-18: `retry` repeats a price or area request once when the connection drops; never a booking. */
    private function call(string $method, string $path, array $data, bool $keepError = false, bool $retry = false): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('biteship_not_configured');
        }

        try {
            $request = Http::acceptJson()
                ->withHeaders(['Authorization' => (string) config('services.biteship.key')])
                ->connectTimeout(3)->timeout((int) config('services.biteship.timeout', 8))
                ->withOptions(['allow_redirects' => false]);
            if ($retry) {
                $request = $request->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false);
            }
            $url = rtrim((string) config('services.biteship.base_url'), '/').$path;
            $response = $method === 'get' ? $request->get($url, $data) : $request->asJson()->post($url, $data);
            $payload = strlen($response->body()) <= 512 * 1024 ? $response->json() : null;
        } catch (Throwable) {
            throw new RuntimeException('biteship_unavailable');
        }

        // "No courier for this route" is an empty price list; on a booking it stays a refusal staff can read.
        if ($path === '/v1/rates/couriers' && $response->status() === 400 && is_array($payload) && ($payload['code'] ?? null) === self::NO_COURIER) {
            return ['pricing' => []];
        }
        if (! $response->successful() || ! is_array($payload) || ($payload['success'] ?? null) !== true) {
            // Booking refusals carry Biteship's short reason for staff; anything else stays generic.
            $reason = $keepError && is_array($payload) && is_string($payload['error'] ?? null) ? mb_substr(strip_tags($payload['error']), 0, 160) : null;
            throw new RuntimeException($reason ?? 'biteship_unavailable');
        }

        return $payload;
    }
}
