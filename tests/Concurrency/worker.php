<?php

/*
 * Concurrency worker (ORD-02e): one process, one database connection. Waits for a shared start time, then sends one
 * signed Order API request through the full HTTP kernel (or one domain action) and prints a JSON result.
 * Used only by tests/Feature/OrderApiConcurrencyTest.php against MySQL/MariaDB.
 *
 * Args: base64(JSON {start_ms, kind, method, path, body, key, secret, user_id, target_id})
 */

use App\Actions\Users\ManageAdminUser;
use App\Models\User;
use App\Support\OrderApi\OrderApiSignature;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

$job = json_decode(base64_decode($argv[1]), true);
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';

// Boot first so every process starts the actual work at the same moment.
$job['kind'] === 'deactivate' ? $app->make(ConsoleKernel::class)->bootstrap() : $app->make(HttpKernel::class)->bootstrap();
while ((int) (microtime(true) * 1000) < $job['start_ms']) {
    usleep(200);
}

if ($job['kind'] === 'deactivate') {
    try {
        $app->make(ManageAdminUser::class)->setActive(User::findOrFail($job['user_id']), User::findOrFail($job['target_id']), false);
        echo json_encode(['status' => 200]);
    } catch (Throwable $error) {
        echo json_encode(['status' => 409, 'code' => class_basename($error)]);
    }
    exit(0);
}

$raw = is_string($job['body']) ? $job['body'] : json_encode($job['body'], JSON_UNESCAPED_SLASHES);
$timestamp = time();
$request = Request::create($job['path'], $job['method'], [], [], [], [
    'HTTP_X_QAMMARIS_CLIENT' => getenv('QAMMARIS_ORDER_API_CLIENT_ID'),
    'HTTP_X_QAMMARIS_TIMESTAMP' => (string) $timestamp,
    'HTTP_X_QAMMARIS_SIGNATURE' => OrderApiSignature::sign($job['secret'], $timestamp, $job['method'], $job['path'], $raw),
    'HTTP_IDEMPOTENCY_KEY' => $job['key'],
    'CONTENT_TYPE' => 'application/json',
], $raw);
$kernel = $app->make(HttpKernel::class);
$response = $kernel->handle($request);
$body = json_decode($response->getContent(), true);
echo json_encode([
    'status' => $response->getStatusCode(),
    'code' => $body['error']['code'] ?? null,
    'revision' => $body['order']['revision'] ?? null,
    'event_id' => $body['event_id'] ?? null,
    'replay' => $response->headers->get('X-Qammaris-Idempotent-Replay') === 'true',
    'body' => $response->getContent(),
]);
$kernel->terminate($request, $response);
