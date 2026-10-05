<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class QammarisAppClient
{
    public function configured(): bool
    {
        $url = parse_url((string) config('qammaris_app.base_url'));

        return is_array($url) && ($url['scheme'] ?? '') === 'https'
            && ! empty($url['host']) && ! isset($url['user']) && ! isset($url['pass'])
            && ! isset($url['query']) && ! isset($url['fragment'])
            && trim((string) config('qammaris_app.api_key')) !== ''
            && trim((string) config('qammaris_app.webhook_secret')) !== '';
    }

    public function changes(int $checkpoint): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('qammaris_app_not_configured');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-Api-Key' => config('qammaris_app.api_key')])
                ->connectTimeout(3)->timeout(8)
                ->withOptions(['allow_redirects' => false])
                ->get(rtrim(config('qammaris_app.base_url'), '/').'/products/changes', [
                    'after_seq' => $checkpoint, 'limit' => 200,
                ]);
            if (! $response->successful() || strlen($response->body()) > 2 * 1024 * 1024) {
                throw new RuntimeException('invalid_response');
            }
            $payload = $response->json();
        } catch (Throwable) {
            // Do not retain HTTP exceptions: they can contain credentials or response bodies.
            throw new RuntimeException('qammaris_app_feed_unavailable');
        }

        if (! is_array($payload) || ! isset($payload['data'], $payload['meta'])
            || ! is_array($payload['data']) || ! array_is_list($payload['data'])
            || count($payload['data']) > 200 || ! is_array($payload['meta'])
            || ! is_int($payload['meta']['next_seq'] ?? null)
            || ! is_bool($payload['meta']['has_more'] ?? null)) {
            throw new RuntimeException('qammaris_app_invalid_feed');
        }

        $next = $payload['meta']['next_seq'];
        $more = $payload['meta']['has_more'];
        if ($next < $checkpoint || $next > 9007199254740991 || ($more && $next === $checkpoint)) {
            throw new RuntimeException('qammaris_app_invalid_checkpoint');
        }

        $previous = $checkpoint;
        $ids = [];
        $data = [];
        foreach ($payload['data'] as $row) {
            $rules = [
                'id' => ['required', 'uuid'],
                'sku' => ['present', 'nullable', 'string', 'max:4096'],
                'name' => ['required', 'string', 'max:4096'],
                'brand' => ['present', 'nullable', 'string', 'max:4096'],
                'department_code' => ['present', 'nullable', 'string', 'max:191'],
                'price' => ['present', 'nullable', 'integer', 'min:0', 'max:9007199254740991'],
                'source' => ['required', Rule::in(['majoo', 'app'])],
                'availability' => ['required', Rule::in(['available', 'sold_out', 'unknown'])],
                'restock_eta' => ['present', 'nullable', 'date_format:Y-m-d'],
                'stock_status_at' => ['present', 'nullable', 'date'],
                'active' => ['required', 'boolean'],
                'merged_into' => ['present', 'nullable', 'uuid'],
                'hidden' => ['required', 'boolean'],
                'revision' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
                'change_seq' => ['required', 'integer', 'min:1', 'max:9007199254740991'],
            ];
            if (! is_array($row) || Validator::make($row, $rules)->fails()
                || ! is_int($row['revision']) || ! is_int($row['change_seq'])
                || ! is_bool($row['active']) || ! is_bool($row['hidden'])
                || ($row['price'] !== null && ! is_int($row['price']))
                || $row['revision'] !== $row['change_seq']
                || $row['change_seq'] <= $previous || $row['change_seq'] > $next
                || isset($ids[$row['id']])
                || $row['hidden'] !== (! $row['active'] || $row['merged_into'] !== null || $row['department_code'] === 'lainnya')) {
                throw new RuntimeException('qammaris_app_invalid_product');
            }
            $previous = $row['change_seq'];
            $ids[$row['id']] = true;
            $data[] = Arr::only($row, array_keys($rules));
        }

        if ($more && $data === []) {
            throw new RuntimeException('qammaris_app_empty_continuation');
        }
        if ($more && $next !== $previous) {
            throw new RuntimeException('qammaris_app_invalid_checkpoint');
        }

        return ['data' => $data, 'next_seq' => $next, 'has_more' => $more];
    }
}
