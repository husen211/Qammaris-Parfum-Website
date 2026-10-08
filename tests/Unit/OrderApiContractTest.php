<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps docs/integrations/QAMMARIS_ORDER_API_V1.md and its OpenAPI file describing the same contract.
 * The validator is deliberately small and strict: an unsupported JSON Schema keyword fails the test
 * instead of being ignored.
 */
class OrderApiContractTest extends TestCase
{
    private const SUPPORTED_KEYWORDS = ['$ref', 'type', 'enum', 'const', 'required', 'properties', 'additionalProperties',
        'items', 'minItems', 'maxItems', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum', 'minProperties',
        'allOf', 'oneOf', 'if', 'then', 'format', 'description', 'default', 'x-error-status'];

    private const METHODS = ['get', 'post', 'put', 'delete', 'patch'];

    private const BASELINE_OPENAPI_SHA256 = 'ee896e7e078df82fa6966141664b7ef152d7be108406f2d57f2769ceb438eeae';

    private array $spec;

    private string $markdown;

    protected function setUp(): void
    {
        $dir = dirname(__DIR__, 2).'/docs/integrations';
        $this->spec = Yaml::parseFile($dir.'/qammaris-order-api-v1.openapi.yaml');
        $this->markdown = file_get_contents($dir.'/QAMMARIS_ORDER_API_V1.md');
    }

    public function test_openapi_baseline_is_frozen(): void
    {
        // API v1 baseline confirmed by both agents (r4.1). Changing the OpenAPI file needs a new revision note,
        // the other agent's approval for breaking changes, and then a deliberate update of this hash.
        $source = str_replace("\r\n", "\n", file_get_contents(dirname(__DIR__, 2).'/docs/integrations/qammaris-order-api-v1.openapi.yaml'));
        $this->assertSame(self::BASELINE_OPENAPI_SHA256, hash('sha256', $source), 'OpenAPI v1 baseline changed: follow contract §14 change control');
        $this->assertStringContainsString('**BASELINE API v1 — dibekukan (r4.1, OpenAPI `1.0.0-rc.4.1`', $this->markdown);
    }

    public function test_versions_match_and_contract_is_the_r4_1_baseline(): void
    {
        $this->assertSame('1.0.0-rc.4.1', $this->spec['info']['version']);
        $this->assertStringContainsString('r4.1', $this->spec['info']['description']);
        // The App review commit is still local to the App machine; it must not be presented as a GitHub link.
        $this->assertStringNotContainsString('github.com/husen211/qammaris-reimbursement-management-system', $this->markdown);
    }

    public function test_webhook_signs_the_agreed_path_and_raw_body(): void
    {
        $webhook = $this->spec['webhooks']['orderChanged']['post'];
        $path = '/api/integrations/website/orders/events';
        $this->assertSame($path, $webhook['x-signature-path']);
        $this->assertStringContainsString("**`path_with_query` = `{$path}`**", $this->markdown);
        $this->assertSame(1, preg_match('/Tujuan `POST (https:\/\/[^`]+)`/', $this->markdown, $url));
        $this->assertSame($path, parse_url($url[1], PHP_URL_PATH), 'Signed path must equal the agreed webhook URL path');
        $this->assertStringContainsString($url[1], $webhook['summary']);

        [$body, $secret] = $this->webhookVectorInputs();
        $event = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        $errors = [];
        $this->validate($event, ['$ref' => '#/components/schemas/WebhookEvent'], 'webhook raw body', $errors);
        $this->assertSame([], $errors, implode(PHP_EOL, $errors));
        $this->assertEquals($this->firstExample('WebhookEvent'), $event, 'Raw body is the documented webhook example');
        $this->assertSame(json_encode($event, JSON_UNESCAPED_SLASHES), $body, 'Raw body is compact JSON, byte for byte');

        foreach ($this->webhookVectors() as $vector) {
            $this->assertSame(hash('sha256', $body), $vector['body_hash']);
            $this->assertSame($this->sign($secret, $vector['timestamp'], 'POST', $path, $body), $vector['signature']);
        }
    }

    public function test_webhook_retry_beyond_timestamp_tolerance_uses_new_headers_and_the_same_event(): void
    {
        $tolerance = $this->spec['x-timestamp-tolerance-seconds'];
        $this->assertSame(300, $tolerance);
        $this->assertStringContainsString('lebih dari 300 detik', $this->markdown);
        $retry = $this->spec['webhooks']['orderChanged']['post']['x-retry'];
        $this->assertTrue($retry['new-timestamp-and-signature-per-attempt']);
        $this->assertEqualsCanonicalizing(['event_id', 'body'], $retry['unchanged']);
        $this->assertSame('event_id', $retry['idempotency-key']);
        $this->assertSame(86400, $retry['give-up-after-seconds']);
        $this->assertSame([0, 60, 300, 900, 3600, 21600], $retry['schedule-seconds']);
        $this->assertStringContainsString('segera, 1m, 5m, 15m, 1j, 6j, sampai 24 jam', $this->markdown);
        $this->assertNotEmpty(array_filter($retry['schedule-seconds'], fn ($delay) => $delay > $tolerance), 'Real retries outlive the tolerance');
        $this->assertStringContainsString('menghitung `X-Qammaris-Timestamp` dan `X-Qammaris-Signature` baru', $this->markdown);

        [$body, $secret] = $this->webhookVectorInputs();
        $path = '/api/integrations/website/orders/events';
        [$first, $second] = $this->webhookVectors();
        $this->assertGreaterThan($tolerance, $second['timestamp'] - $first['timestamp']);
        $this->assertSame($first['body_hash'], $second['body_hash'], 'A retry resends the same raw body');
        $this->assertNotSame($first['signature'], $second['signature']);

        $now = $second['timestamp'];
        $this->assertTrue($this->verify($secret, $first['timestamp'], $first['signature'], $body, $path, $first['timestamp']), 'First attempt is valid when sent');
        $this->assertFalse($this->verify($secret, $first['timestamp'], $first['signature'], $body, $path, $now), 'Reused headers are stale on retry');
        $this->assertFalse($this->verify($secret, $second['timestamp'], $first['signature'], $body, $path, $now), 'An old signature with a new timestamp fails');
        $this->assertTrue($this->verify($secret, $second['timestamp'], $second['signature'], $body, $path, $now), 'Fresh retry headers are accepted');
        $edge = $now - $tolerance;
        $this->assertTrue($this->verify($secret, $edge, $this->sign($secret, $edge, 'POST', $path, $body), $body, $path, $now), 'Exactly 300 s old is accepted');
        $this->assertFalse($this->verify($secret, $edge - 1, $this->sign($secret, $edge - 1, 'POST', $path, $body), $body, $path, $now), '301 s old is stale');
        // Signing with the host or another base path never verifies.
        $this->assertFalse($this->verify($secret, $now, $this->sign($secret, $now, 'POST', 'https://api.qammarisapp.com'.$path, $body), $body, $path, $now));
    }

    public function test_every_ref_resolves_and_every_component_schema_is_used(): void
    {
        $refs = [];
        array_walk_recursive($this->spec, function ($value, $key) use (&$refs) {
            if ($key === '$ref') {
                $refs[] = $value;
            }
        });
        $this->assertNotEmpty($refs);
        foreach (array_unique($refs) as $ref) {
            $this->assertNotNull($this->resolve($ref), "Unresolvable \$ref {$ref}");
        }

        foreach (array_keys($this->spec['components']['schemas']) as $name) {
            $this->assertContains("#/components/schemas/{$name}", $refs, "Orphan schema {$name}");
        }
    }

    public function test_schemas_use_only_keywords_the_validator_understands(): void
    {
        foreach ($this->spec['components']['schemas'] as $name => $schema) {
            $this->assertKeywords($schema, "components.schemas.{$name}");
        }
    }

    public function test_every_markdown_json_example_is_tagged_and_valid_against_its_schema(): void
    {
        preg_match_all('/```json\n/', $this->markdown, $blocks);
        $examples = $this->examples();
        $this->assertCount(count($blocks[0]), $examples, 'Every ```json example needs a preceding <!-- validate: Schema --> tag');
        $this->assertGreaterThanOrEqual(16, count($examples));

        foreach ($examples as $i => [$schemaName, $data]) {
            $this->assertArrayHasKey($schemaName, $this->spec['components']['schemas'], "Unknown schema {$schemaName}");
            $errors = [];
            $this->validate($data, ['$ref' => "#/components/schemas/{$schemaName}"], "example#{$i}({$schemaName})", $errors);
            $this->assertSame([], $errors, implode("\n", $errors));
        }
    }

    public function test_validator_rejects_invalid_payloads(): void
    {
        $actor = ['app_user_id' => '665f0c2a9b1e4a0012ab34cd', 'display_name' => 'Andi', 'app_role' => 'employee'];
        $invalid = [
            'Actor' => [['app_user_id' => 'ABC', 'display_name' => 'Andi', 'app_role' => 'employee'],
                $actor + ['permission' => 'orders.handle'], array_merge($actor, ['app_role' => 'mitra'])],
            'PreparationRequest' => [['status' => 'packed', 'expected_revision' => 3, 'actor' => $actor]],
            'CostRequest' => [$this->cost(['reimbursement' => ['status' => 'approved', 'amount' => 5000, 'proof' => 'pending',
                'waiver' => null, 'updated_at' => '2026-10-08T03:10:00Z']]),
                $this->cost(['reimbursement' => ['status' => 'approved', 'amount' => 5000, 'proof' => 'waived',
                    'waiver' => null, 'updated_at' => '2026-10-08T03:10:00Z']]),
                $this->cost(['funding' => [['source' => 'owner_transfer', 'amount' => 20000]]]),
                $this->cost(['status' => 'deleted'])],
            'JntRequest' => [['expected_revision' => 3, 'actor' => $actor]],
            'WebhookEvent' => [['event_id' => '01JABCF0QWERTYUIOPASDFGHJK', 'type' => 'order.changed',
                'order_id' => '01JABCDE2F3G4H5J6K7M8N9P0Q', 'revision' => 1, 'occurred_at' => '2026-10-08T03:15:00Z']],
        ];

        foreach ($invalid as $schema => $payloads) {
            foreach ($payloads as $i => $payload) {
                $errors = [];
                $this->validate($this->toJson($payload), ['$ref' => "#/components/schemas/{$schema}"], $schema, $errors);
                $this->assertNotEmpty($errors, "{$schema} payload #{$i} should be rejected");
            }
        }

        $errors = [];
        $this->validate($this->toJson($this->cost([])), ['$ref' => '#/components/schemas/CostRequest'], 'CostRequest', $errors);
        $this->assertSame([], $errors, 'Baseline cost fixture must be valid');
    }

    public function test_order_example_follows_business_rules(): void
    {
        $order = $this->firstExample('Order');
        $lines = [];
        $subtotal = 0;
        foreach ($order->items as $item) {
            $this->assertSame($item->unit_price * $item->quantity, $item->line_total);
            $lines[$item->line_id] = $item->quantity;
            $subtotal += $item->line_total;
        }
        $this->assertSame($subtotal, $order->totals->subtotal);
        $this->assertSame($subtotal + $order->totals->adjustments + $order->totals->shipping_charge, $order->totals->total);

        $packed = [];
        foreach ($order->fulfillment->preparation->packed_items as $packedItem) {
            $packed[$packedItem->line_id] = $packedItem->quantity;
        }
        $this->assertEquals($lines, $packed, 'packed_items must cover every line with the ordered quantity');
        $preparation = $this->firstExample('PreparationRequest');
        $this->assertEquals($lines, array_column(array_map(fn ($p) => (array) $p, $preparation->packed_items), 'quantity', 'line_id'));

        foreach ($order->costs as $cost) {
            $this->assertCostArithmetic($cost);
        }
        $this->assertEquals($this->deriveObligations($order), array_map(fn ($o) => (array) $o, $order->obligations));
        $this->assertSame($this->queueFor($order), $order->queue);
        $this->assertSame('https://qammarisapp.com/orders/'.$order->id, $order->links->app_task);

        if ($order->lifecycle === 'completed') {
            $this->assertSame('handed_over', $order->fulfillment->handover->status);
            $this->assertSame('paid', $order->payment->status);
            $this->assertEmpty(array_filter($order->issues, fn ($issue) => $issue->status === 'open'));
            foreach ($order->obligations as $obligation) {
                $this->assertFalse($obligation->status === 'open' && $obligation->blocks_completion, 'Open blocking obligation on a completed order');
            }
            // Owner R8: an unpaid staff reimbursement does not hold back completion but stays visible.
            $this->assertTrue($order->flags->open_reimbursement);
        }

        $summary = $this->firstExample('OrderListPage')->data[0];
        foreach (['id', 'number', 'revision', 'updated_at', 'lifecycle', 'queue'] as $field) {
            $this->assertSame($order->{$field}, $summary->{$field});
        }
        $this->assertEquals($order->flags, $summary->flags);
    }

    public function test_cost_examples_balance_funding_and_reimbursement(): void
    {
        $costs = array_filter($this->examples(), fn ($example) => $example[0] === 'CostRequest');
        $this->assertCount(2, $costs);
        foreach ($costs as [, $cost]) {
            $this->assertCostArithmetic($cost);
        }
    }

    public function test_queue_rules_are_ordered_and_cover_every_queue_value(): void
    {
        $queues = $this->spec['components']['schemas']['Queue']['enum'];
        $this->assertSame($queues, array_slice($this->backticks($this->sectionLine('Nilai `queue`:')), 1));

        $tableQueues = [];
        foreach ($this->tableRows('## 9.') as $cells) {
            $tableQueues[] = $this->backticks($cells[2])[0];
        }
        $this->assertSame(['null', 'has_issue', 'done', 'done', 'in_delivery', 'awaiting_pickup', 'ready', 'preparing', 'needs_handling'], $tableQueues);
        $this->assertEqualsCanonicalizing($queues, array_values(array_unique(array_diff($tableQueues, ['null']))));

        $order = fn (array $over) => $this->toJson(array_replace_recursive([
            'lifecycle' => 'active', 'issues' => [], 'claims' => ['preparation' => null],
            'fulfillment' => ['type' => 'local_delivery', 'preparation' => ['status' => 'not_started'], 'courier' => ['status' => 'unassigned'],
                'jnt' => null, 'handover' => ['status' => 'pending'], 'delivery' => ['status' => 'unconfirmed']],
        ], $over));
        $cases = [
            [['lifecycle' => 'awaiting_customer'], null],
            [['issues' => [['status' => 'open']], 'lifecycle' => 'completed'], 'has_issue'],
            [['lifecycle' => 'completed'], 'done'],
            [['fulfillment' => ['type' => 'pickup', 'handover' => ['status' => 'handed_over']]], 'done'],
            [['fulfillment' => ['handover' => ['status' => 'handed_over']]], 'in_delivery'],
            [['fulfillment' => ['preparation' => ['status' => 'packed'], 'courier' => ['status' => 'arrived']]], 'awaiting_pickup'],
            [['fulfillment' => ['type' => 'intercity', 'preparation' => ['status' => 'packed'], 'courier' => null, 'jnt' => ['status' => 'qr_available']]], 'awaiting_pickup'],
            [['fulfillment' => ['preparation' => ['status' => 'packed']]], 'ready'],
            [['claims' => ['preparation' => ['holder' => []]]], 'preparing'],
            [[], 'needs_handling'],
        ];
        foreach ($cases as $i => [$over, $expected]) {
            $this->assertSame($expected, $this->queueFor($order($over)), "queue case #{$i}");
        }
    }

    public function test_markdown_enums_match_openapi(): void
    {
        $schemas = $this->spec['components']['schemas'];
        $rows = [];
        foreach ($this->tableRows('## 7.') as $cells) {
            $rows[trim($cells[0], ' `')] = $this->backticks($cells[1]);
        }
        $this->assertSame($schemas['Lifecycle']['enum'], $rows['lifecycle']);
        $this->assertSame($schemas['PaymentStatus']['enum'], $rows['payment.status']);
        $this->assertSame($schemas['PreparationStatus']['enum'], $rows['preparation.status']);
        $this->assertSame($schemas['CourierStatus']['enum'], $rows['courier.status']);
        $this->assertSame($schemas['JntStatus']['enum'], $rows['jnt.status']);
        $this->assertSame(array_merge($schemas['HandoverStatus']['enum'], $schemas['HandedTo']['enum']), $rows['handover.status']);
        $this->assertSame($schemas['DeliveryStatus']['enum'], $rows['delivery.status']);
        $this->assertSame($schemas['Obligation']['properties']['type']['enum'], $rows['obligations[]']);

        $sources = array_map(fn ($cells) => trim($cells[0], ' `'), $this->tableRows('| `source` |'));
        $this->assertSame($schemas['FundingSource']['enum'], $sources);
        $this->assertSame($schemas['ReimbursementStatus']['enum'], array_slice($this->backticks($this->sectionLine('  - `status` ∈')), 1));
        $this->assertSame($schemas['ProofStatus']['enum'], array_slice($this->backticks($this->sectionLine('  - `proof` ∈')), 1));
        $this->assertSame($schemas['CostStatus']['enum'], array_slice($this->backticks($this->sectionLine('- `status`: `active`')), 1));
        $this->assertSame(['owner', 'employee'], $schemas['Actor']['properties']['app_role']['enum']);
        $this->assertArrayNotHasKey('permission', $schemas['Actor']['properties']);
    }

    public function test_error_table_matches_error_schema_and_status_map(): void
    {
        $error = $this->spec['components']['schemas']['Error'];
        $table = [];
        foreach ($this->tableRows('## 10.') as $cells) {
            $table[trim($cells[1], ' `')] = (int) $cells[0];
        }
        $this->assertSame($error['properties']['error']['properties']['code']['enum'], array_keys($table));
        $this->assertSame($error['x-error-status'], $table);
    }

    public function test_endpoint_table_matches_operations_request_schemas_and_revision_rules(): void
    {
        $operations = [];
        foreach ($this->spec['paths'] as $path => $item) {
            foreach (array_intersect_key($item, array_flip(self::METHODS)) as $method => $operation) {
                $operations[strtoupper($method).' '.$path] = $operation;
            }
        }

        $documented = [];
        foreach ($this->tableRows('| Method | Path |') as $cells) {
            $key = $cells[0].' '.strtok(trim($cells[1], ' `'), '?');
            $documented[] = $key;
            $this->assertArrayHasKey($key, $operations, "Markdown endpoint {$key} missing in OpenAPI");
            $operation = $operations[$key];
            $schema = $this->requestSchema($operation);

            if (str_contains($cells[2], 'multipart')) {
                $this->assertArrayHasKey('multipart/form-data', $operation['requestBody']['content']);
            } elseif (trim($cells[2]) === '—') {
                $this->assertArrayNotHasKey('requestBody', $operation, $key);
            } else {
                $this->assertSame("#/components/schemas/{$this->backticks($cells[2])[0]}", $operation['requestBody']['content']['application/json']['schema']['$ref'], $key);
            }

            $requiresRevision = $schema !== null && in_array('expected_revision', $schema['required'] ?? [], true);
            $this->assertSame(str_starts_with(trim($cells[3]), 'wajib'), $requiresRevision, "expected_revision rule for {$key}");
            if ($schema !== null && ! $requiresRevision) {
                $this->assertArrayHasKey('expected_revision', $schema['properties'], "Optional expected_revision for {$key}");
            }
        }
        $this->assertEqualsCanonicalizing(array_keys($operations), $documented);
    }

    public function test_every_operation_declares_its_errors_and_responses(): void
    {
        $error = $this->spec['components']['schemas']['Error'];
        $codes = $error['properties']['error']['properties']['code']['enum'];
        $statusFor = $error['x-error-status'];
        $used = array_merge($this->spec['x-common-error-codes'], $this->spec['x-mutation-error-codes']);

        foreach ($this->spec['paths'] as $path => $item) {
            foreach (array_intersect_key($item, array_flip(self::METHODS)) as $method => $operation) {
                $label = strtoupper($method).' '.$path;
                $mutation = $method !== 'get';
                $own = $operation['x-error-codes'] ?? null;
                $this->assertIsArray($own, "{$label} lacks x-error-codes");
                $all = array_merge($own, $this->spec['x-common-error-codes'], $mutation ? $this->spec['x-mutation-error-codes'] : []);
                $used = array_merge($used, $own);

                foreach ($all as $code) {
                    $this->assertContains($code, $codes, "{$label} uses unknown code {$code}");
                    $status = $statusFor[$code];
                    $declared = array_key_exists($status, $operation['responses']) || ($status >= 500 && isset($operation['responses']['default']));
                    $this->assertTrue($declared, "{$label} returns {$code} ({$status}) without a declared response");
                    if ($status < 500) {
                        $this->assertSame('#/components/responses/Error', $operation['responses'][$status]['$ref']);
                    }
                }
                $this->assertArrayHasKey('default', $operation['responses']);

                if (str_contains($path, '{id}')) {
                    $this->assertContains('order_not_found', $own, $label);
                }
                if ($mutation) {
                    $this->assertSame('#/components/responses/Mutation', $operation['responses'][200]['$ref'], $label);
                    $this->assertContains(['$ref' => '#/components/parameters/IdempotencyKey'], $operation['parameters'], $label);
                    $this->assertContains('revision_conflict', $own, $label);
                }
            }
        }

        $this->assertContains('task_already_claimed', $this->spec['paths']['/orders/{id}/claims']['post']['x-error-codes']);
        $cost = $this->spec['paths']['/orders/{id}/costs/{expense_ref}']['put']['x-error-codes'];
        $this->assertContains('proof_required', $cost);
        $this->assertContains('expense_already_linked', $cost);
        $this->assertEqualsCanonicalizing($codes, array_values(array_unique($used)), 'Every error code must be reachable from some operation');
    }

    public function test_hmac_test_vectors_reproduce(): void
    {
        $this->assertSame(1, preg_match('/secret contoh `([^`]+)`, timestamp `(\d+)`/', $this->markdown, $m));
        [, $secret, $timestamp] = $m;
        $rows = $this->tableRows('| Method | `path_with_query` |');
        $this->assertCount(2, $rows);

        foreach ($rows as $cells) {
            $ticks = $this->backticks($cells[1]);
            $path = $ticks[0];
            $body = str_contains($cells[1], 'dengan body') ? $ticks[1] : '';
            $bodyHash = hash('sha256', $body);
            $this->assertSame(trim($cells[2], ' `'), $bodyHash, "Body hash for {$cells[0]} {$path}");
            $signature = hash_hmac('sha256', $timestamp."\n".$cells[0]."\n".$path."\n".$bodyHash, $secret);
            $this->assertSame(trim($cells[3], ' `'), $signature, "Signature for {$cells[0]} {$path}");
            $this->assertStringStartsWith('/integrations/qammaris-app/orders/v1/', $path);
        }
    }

    /** Reference receiver check (contract §3): timestamp within tolerance and constant-time signature match. */
    private function verify(string $secret, int $timestamp, string $signature, string $body, string $path, int $now): bool
    {
        return abs($now - $timestamp) <= $this->spec['x-timestamp-tolerance-seconds']
            && hash_equals($this->sign($secret, $timestamp, 'POST', $path, $body), $signature);
    }

    private function sign(string $secret, int $timestamp, string $method, string $path, string $body): string
    {
        return hash_hmac('sha256', implode("\n", [$timestamp, $method, $path, hash('sha256', $body)]), $secret);
    }

    /** @return array{0: string, 1: string} raw body, secret */
    private function webhookVectorInputs(): array
    {
        $this->assertSame(1, preg_match('/<!-- hmac-raw-body: webhook -->\n```text\n(.+?)\n```/s', $this->markdown, $body));
        $this->assertSame(1, preg_match('/Vektor uji webhook\*\* \(secret contoh `([^`]+)`/', $this->markdown, $secret));

        return [$body[1], $secret[1]];
    }

    /** @return list<array{timestamp: int, body_hash: string, signature: string}> */
    private function webhookVectors(): array
    {
        $rows = $this->tableRows('| Pengiriman | `X-Qammaris-Timestamp` |');
        $this->assertCount(2, $rows);

        return array_map(fn ($cells) => [
            'timestamp' => (int) trim($cells[1], ' `'),
            'body_hash' => trim($cells[2], ' `'),
            'signature' => trim($cells[3], ' `'),
        ], $rows);
    }

    private function cost(array $override): array
    {
        return array_replace([
            'kind' => 'actual_shipping', 'status' => 'active', 'amount' => 20000,
            'funding' => [['source' => 'customer_cash_held', 'amount' => 15000], ['source' => 'staff_advance', 'amount' => 5000]],
            'reimbursement' => ['status' => 'submitted', 'amount' => 5000, 'proof' => 'attached', 'waiver' => null, 'updated_at' => '2026-10-08T03:10:00Z'],
            'source_version' => 2,
            'actor' => ['app_user_id' => '665f0c2a9b1e4a0012ab34cd', 'display_name' => 'Andi', 'app_role' => 'employee'],
        ], $override);
    }

    private function assertCostArithmetic(stdClass $cost): void
    {
        $sources = array_column(array_map(fn ($f) => (array) $f, $cost->funding), 'amount', 'source');
        $this->assertCount(count($cost->funding), $sources, 'One element per funding source');
        $this->assertSame($cost->amount, array_sum($sources), 'Σ funding.amount = amount');
        $advance = $sources['staff_advance'] ?? 0;
        $this->assertSame($advance, $cost->reimbursement->amount, 'reimbursement.amount = staff_advance part');
        $this->assertSame($advance === 0, $cost->reimbursement->status === 'not_applicable');
    }

    /** Website-derived reimbursement obligations from costs (contract §8.5). */
    private function deriveObligations(stdClass $order): array
    {
        $obligations = [];
        if (in_array($order->payment->status, ['refund_pending', 'refunded'], true)) {
            $this->fail('Refund derivation is not covered by the example');
        }
        foreach ($order->costs as $cost) {
            if ($cost->reimbursement->amount === 0) {
                continue;
            }
            $open = $cost->status === 'active' && in_array($cost->reimbursement->status, ['awaiting_proof', 'submitted', 'approved'], true);
            $obligations[] = ['type' => 'reimbursement', 'status' => $open ? 'open' : 'settled',
                'amount' => $cost->reimbursement->amount, 'ref' => $cost->expense_ref, 'blocks_completion' => false];
        }

        return $obligations;
    }

    /** Reference implementation of the ordered queue rules in contract §9. */
    private function queueFor(stdClass $order): ?string
    {
        $f = $order->fulfillment;
        if (in_array($order->lifecycle, ['draft', 'awaiting_customer', 'cancelled'], true)) {
            return null;
        }
        if (array_filter($order->issues, fn ($issue) => $issue->status === 'open')) {
            return 'has_issue';
        }
        if ($order->lifecycle === 'completed') {
            return 'done';
        }
        if ($f->handover->status === 'handed_over') {
            return $f->type === 'pickup' || $f->delivery->status === 'delivered' ? 'done' : 'in_delivery';
        }
        if ($f->preparation->status === 'packed') {
            $pickup = in_array($f->courier?->status, ['requested', 'arrived'], true)
                || in_array($f->jnt?->status, ['pickup_requested', 'qr_available'], true);

            return $pickup ? 'awaiting_pickup' : 'ready';
        }
        if ($f->preparation->status === 'preparing' || $order->claims->preparation !== null) {
            return 'preparing';
        }

        return 'needs_handling';
    }

    /** @return list<array{0: string, 1: mixed}> */
    private function examples(): array
    {
        preg_match_all('/<!-- validate: (\w+) -->\n```json\n(.*?)\n```/s', $this->markdown, $matches, PREG_SET_ORDER);

        return array_map(function ($match) {
            $data = json_decode($match[2], false, 512, JSON_THROW_ON_ERROR);

            return [$match[1], $data];
        }, $matches);
    }

    private function firstExample(string $schema): mixed
    {
        foreach ($this->examples() as [$name, $data]) {
            if ($name === $schema) {
                return $data;
            }
        }
        $this->fail("No {$schema} example");
    }

    /** Rows (cells without the outer pipes) of the first Markdown table after $anchor, header and separator skipped. */
    private function tableRows(string $anchor): array
    {
        $start = strpos($this->markdown, $anchor);
        $this->assertNotFalse($start, "Anchor {$anchor} not found");
        $lines = explode("\n", substr($this->markdown, $start));
        $rows = [];
        $inTable = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '|')) {
                $inTable = true;
                $cells = array_map('trim', explode('|', trim($line, '|')));
                $rows[] = $cells;
            } elseif ($inTable) {
                break;
            }
        }

        return array_slice($rows, 2);
    }

    private function sectionLine(string $prefix): string
    {
        foreach (explode("\n", $this->markdown) as $line) {
            if (str_starts_with($line, $prefix)) {
                return $line;
            }
        }
        $this->fail("Line starting with {$prefix} not found");
    }

    private function backticks(string $text): array
    {
        preg_match_all('/`([^`]+)`/', $text, $m);

        return $m[1];
    }

    private function requestSchema(array $operation): ?array
    {
        $content = $operation['requestBody']['content'] ?? null;
        if ($content === null) {
            return null;
        }
        $schema = ($content['application/json'] ?? $content['multipart/form-data'])['schema'];

        return isset($schema['$ref']) ? $this->resolve($schema['$ref']) : $schema;
    }

    private function resolve(string $ref): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null;
        }
        $node = $this->spec;
        foreach (explode('/', substr($ref, 2)) as $part) {
            if (! is_array($node) || ! array_key_exists($part, $node)) {
                return null;
            }
            $node = $node[$part];
        }

        return is_array($node) ? $node : null;
    }

    private function assertKeywords(array $schema, string $path): void
    {
        foreach ($schema as $keyword => $value) {
            $this->assertContains($keyword, self::SUPPORTED_KEYWORDS, "Unsupported keyword {$keyword} at {$path}");
            match ($keyword) {
                'properties' => array_walk($value, fn ($sub, $name) => $this->assertKeywords($sub, "{$path}.{$name}")),
                'allOf', 'oneOf' => array_walk($value, fn ($sub, $i) => $this->assertKeywords($sub, "{$path}.{$keyword}[{$i}]")),
                'items', 'if', 'then' => $this->assertKeywords($value, "{$path}.{$keyword}"),
                default => null,
            };
        }
    }

    private function toJson(mixed $value): mixed
    {
        return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private function isValid(mixed $data, array $schema): bool
    {
        $errors = [];
        $this->validate($data, $schema, '', $errors);

        return $errors === [];
    }

    private function validate(mixed $data, array $schema, string $path, array &$errors): void
    {
        if (isset($schema['$ref'])) {
            $resolved = $this->resolve($schema['$ref']);
            if ($resolved === null) {
                $errors[] = "{$path}: unresolvable {$schema['$ref']}";

                return;
            }
            $schema = $resolved + array_diff_key($schema, ['$ref' => true]);
        }

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            if (! array_filter($types, fn ($type) => $this->hasType($data, $type))) {
                $errors[] = "{$path}: expected ".implode('|', $types).', got '.get_debug_type($data);

                return;
            }
        }
        if (array_key_exists('enum', $schema) && ! in_array($data, $schema['enum'], true)) {
            $errors[] = "{$path}: ".json_encode($data).' not in enum';
        }
        if (array_key_exists('const', $schema) && $data !== $schema['const']) {
            $errors[] = "{$path}: expected const ".json_encode($schema['const']);
        }

        if (is_string($data)) {
            $length = mb_strlen($data);
            if (isset($schema['minLength']) && $length < $schema['minLength']) {
                $errors[] = "{$path}: shorter than {$schema['minLength']}";
            }
            if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
                $errors[] = "{$path}: longer than {$schema['maxLength']}";
            }
            if (isset($schema['pattern']) && ! preg_match('~'.str_replace('~', '\~', $schema['pattern']).'~u', $data)) {
                $errors[] = "{$path}: '{$data}' does not match {$schema['pattern']}";
            }
            if (($schema['format'] ?? null) === 'date-time'
                && ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $data)) {
                $errors[] = "{$path}: '{$data}' is not RFC 3339 date-time";
            }
        }
        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "{$path}: below {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "{$path}: above {$schema['maximum']}";
            }
        }
        if ($data instanceof stdClass) {
            $props = get_object_vars($data);
            foreach ($schema['required'] ?? [] as $required) {
                if (! array_key_exists($required, $props)) {
                    $errors[] = "{$path}: missing {$required}";
                }
            }
            if (isset($schema['minProperties']) && count($props) < $schema['minProperties']) {
                $errors[] = "{$path}: fewer than {$schema['minProperties']} properties";
            }
            foreach ($props as $name => $value) {
                if (isset($schema['properties'][$name])) {
                    $this->validate($value, $schema['properties'][$name], "{$path}.{$name}", $errors);
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = "{$path}: unexpected property {$name}";
                }
            }
        }
        if (is_array($data)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[] = "{$path}: fewer than {$schema['minItems']} items";
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[] = "{$path}: more than {$schema['maxItems']} items";
            }
            foreach ($data as $i => $item) {
                if (isset($schema['items'])) {
                    $this->validate($item, $schema['items'], "{$path}[{$i}]", $errors);
                }
            }
        }

        foreach ($schema['allOf'] ?? [] as $sub) {
            $this->validate($data, $sub, $path, $errors);
        }
        if (isset($schema['oneOf'])) {
            $matches = count(array_filter($schema['oneOf'], fn ($sub) => $this->isValid($data, $sub)));
            if ($matches !== 1) {
                $errors[] = "{$path}: matches {$matches} oneOf branches";
            }
        }
        if (isset($schema['if'], $schema['then']) && $this->isValid($data, $schema['if'])) {
            $this->validate($data, $schema['then'], $path, $errors);
        }
    }

    private function hasType(mixed $data, string $type): bool
    {
        return match ($type) {
            'object' => $data instanceof stdClass,
            'array' => is_array($data) && array_is_list($data),
            'string' => is_string($data),
            'integer' => is_int($data),
            'number' => is_int($data) || is_float($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            default => throw new \LogicException("Unknown type {$type}"),
        };
    }
}
