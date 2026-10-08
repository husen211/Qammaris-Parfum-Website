<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Models\OnlineOrderClaim;
use App\Models\OnlineOrderCost;
use App\Models\OnlineOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/**
 * Real concurrency (ORD-02e): several PHP processes with their own DB connections hit the same rows at the same
 * moment. MySQL/MariaDB only — SQLite serialises writers and cannot show these races. Run with DB_CONNECTION=mysql.
 */
class OrderApiConcurrencyTest extends TestCase
{
    use BuildsV2Orders;

    private const SECRET = 'concurrency-secret-not-real-000001';

    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concurrency needs MySQL/MariaDB (separate connections and row locks); SQLite results do not represent production.');
        }
        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            self::$migrated = true;
        }
    }

    /** These tests commit real rows; leave an empty schema for the transactional tests that follow. */
    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        parent::tearDown();
    }

    public function test_six_simultaneous_claims_have_exactly_one_holder(): void
    {
        $order = $this->v2Order();
        $results = $this->race(array_map(fn ($i) => $this->api('POST', $order, '/claims', [
            'task' => 'preparation', 'actor' => $this->actor(sprintf('%024x', 1000 + $i), 'Staf '.$i),
        ]), range(1, 6)));

        $this->assertSame([200], array_values(array_unique(array_column(array_filter($results, fn ($r) => $r['status'] === 200), 'status'))));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 200), json_encode($results));
        $this->assertCount(5, array_filter($results, fn ($r) => $r['code'] === 'task_already_claimed'), json_encode(array_map(fn ($r) => [$r['status'], $r['code']], $results)));
        $this->assertSame(1, OnlineOrderClaim::where('online_order_id', $order->id)->count());
        $this->assertSame($order->revision + 1, $order->fresh()->revision);
    }

    public function test_six_identical_requests_with_one_key_apply_once(): void
    {
        $order = $this->v2Order();
        $key = 'race-key-'.Str::random(16);
        $job = $this->api('POST', $order, '/claims', ['task' => 'handover', 'actor' => $this->actor()], $key);
        $results = $this->race(array_fill(0, 6, $job));

        $this->assertSame([200], array_values(array_unique(array_column($results, 'status'))), json_encode($results));
        $this->assertCount(1, array_unique(array_column($results, 'body')), 'Every caller gets the same stored response');
        $this->assertSame(5, count(array_filter($results, fn ($r) => $r['replay'])));
        $this->assertSame($order->revision + 1, $order->fresh()->revision, 'Applied exactly once');
        $this->assertSame(1, OnlineOrderEvent::where('online_order_id', $order->id)->where('kind', 'claimed')->count());
    }

    public function test_six_packs_with_the_same_expected_revision_have_one_winner(): void
    {
        $order = $this->v2Order();
        $this->race([$this->api('POST', $order, '/claims', ['task' => 'preparation', 'actor' => $this->actor()])]);
        $revision = $order->fresh()->revision;
        $items = $order->items->map(fn ($item) => ['line_id' => $item->line_id, 'quantity' => $item->quantity])->all();
        $results = $this->race(array_map(fn () => $this->api('POST', $order, '/preparation', [
            'status' => 'packed', 'packed_items' => $items, 'expected_revision' => $revision, 'actor' => $this->actor(),
        ]), range(1, 6)));

        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 200), json_encode($results));
        $this->assertCount(5, array_filter($results, fn ($r) => $r['code'] === 'revision_conflict'));
        $this->assertSame([$revision + 1, 'packed'], [$order->fresh()->revision, $order->fresh()->preparation_status]);
    }

    public function test_one_expense_ref_raced_onto_two_orders_links_once(): void
    {
        [$first, $second] = [$this->v2Order(), $this->v2Order()];
        $cost = fn () => ['kind' => 'actual_shipping', 'status' => 'active', 'amount' => 10000, 'funding' => [['source' => 'staff_advance', 'amount' => 10000]],
            'reimbursement' => ['status' => 'submitted', 'amount' => 10000, 'proof' => 'attached', 'waiver' => null, 'updated_at' => '2026-10-09T03:10:00Z'],
            'source_version' => 1, 'actor' => $this->actor()];
        $ref = 'exp_race_'.Str::random(12);
        $results = $this->race([$this->api('PUT', $first, '/costs/'.$ref, $cost()), $this->api('PUT', $second, '/costs/'.$ref, $cost())]);

        $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'), json_encode($results));
        $this->assertContains('expense_already_linked', array_column($results, 'code'));
        $this->assertSame(1, OnlineOrderCost::where('expense_ref', $ref)->count());
    }

    public function test_two_super_admins_deactivating_each_other_leave_one_active(): void
    {
        $a = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $b = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        User::query()->where('role', User::ROLE_SUPER_ADMIN)->whereNotIn('id', [$a->id, $b->id])->update(['is_active' => false]);
        $results = $this->race([
            ['kind' => 'deactivate', 'user_id' => $a->id, 'target_id' => $b->id],
            ['kind' => 'deactivate', 'user_id' => $b->id, 'target_id' => $a->id],
        ]);

        $this->assertSame(1, User::query()->where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->count(), json_encode($results));
        $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'));
    }

    private function api(string $method, OnlineOrder $order, string $suffix, array $body, ?string $key = null): array
    {
        return ['kind' => 'api', 'method' => $method, 'path' => '/integrations/qammaris-app/orders/v1/orders/'.$order->public_id.$suffix,
            'body' => $body, 'key' => $key ?? 'race-'.Str::random(20), 'secret' => self::SECRET];
    }

    /** Starts every job in its own process and releases them together. */
    private function race(array $jobs): array
    {
        $start = (int) (microtime(true) * 1000) + 2500;
        $env = [
            'QAMMARIS_ORDER_API_ENABLED' => 'true', 'QAMMARIS_ORDER_API_CLIENT_ID' => 'race-client', 'QAMMARIS_ORDER_API_SECRET' => self::SECRET,
            'QAMMARIS_ORDER_WEBHOOK_ENABLED' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr', 'APP_ENV' => 'testing',
        ];
        $processes = array_map(function (array $job) use ($start, $env) {
            $process = new Process([PHP_BINARY, base_path('tests/Concurrency/worker.php'), base64_encode(json_encode($job + ['start_ms' => $start]))], base_path(), $env, null, 120);
            $process->start();

            return $process;
        }, $jobs);

        return array_map(function (Process $process) {
            $process->wait();
            $result = json_decode(trim($process->getOutput()), true);
            $this->assertIsArray($result, 'Worker failed: '.$process->getErrorOutput().$process->getOutput());

            return $result + ['code' => null, 'replay' => false, 'body' => null];
        }, $processes);
    }
}
