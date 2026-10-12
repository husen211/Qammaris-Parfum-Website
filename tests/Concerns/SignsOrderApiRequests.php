<?php

namespace Tests\Concerns;

use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderApiSignature;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/** Signed App → Website calls (contract r4.1 §3) and OpenAPI response checks for Order API tests. */
trait SignsOrderApiRequests
{
    protected const API = '/integrations/qammaris-app/orders/v1';

    protected const API_SECRET = 'test-order-api-secret-not-real-0001';

    protected function enableOrderApi(array $overrides = []): void
    {
        config(array_merge([
            'orders_api.enabled' => true, 'orders_api.client_id' => 'qammaris-app-test',
            'orders_api.secret' => self::API_SECRET, 'orders_api.secret_previous' => '',
        ], $overrides));
    }

    /** @param  array<string, mixed>|string|null  $body  arrays are JSON-encoded; strings are sent byte for byte */
    protected function orderApi(string $method, string $path, array|string|null $body = null, array $headers = [], ?string $secret = null, ?int $timestamp = null): TestResponse
    {
        $raw = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $body;
        $uri = str_starts_with($path, '/integrations') ? $path : self::API.$path;
        // Sign exactly the path+query the server will see.
        $seen = Request::create($uri, $method)->getRequestUri();
        $timestamp ??= now()->timestamp;
        $signature = OrderApiSignature::sign($secret ?? self::API_SECRET, $timestamp, $method, $seen, $raw);
        $server = [
            'HTTP_X_QAMMARIS_CLIENT' => 'qammaris-app-test',
            'HTTP_X_QAMMARIS_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_QAMMARIS_SIGNATURE' => $signature,
            'CONTENT_TYPE' => $headers['Content-Type'] ?? 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, [], [], [], $server, $raw);
    }

    protected function assertMatchesApiSchema(TestResponse $response, string $schema): object
    {
        $data = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $errors = OrderApiSchema::errors($data, $schema);
        $this->assertSame([], $errors, "{$schema}: ".implode('; ', $errors));

        return $data;
    }

    protected function assertApiError(TestResponse $response, int $status, string $code): object
    {
        $response->assertStatus($status);
        $data = $this->assertMatchesApiSchema($response, 'Error');
        $this->assertSame($code, $data->error->code);
        $this->assertSame('1', $response->headers->get('X-Qammaris-Api-Version'));

        return $data;
    }
}
