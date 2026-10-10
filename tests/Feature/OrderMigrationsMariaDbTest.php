<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ORD-02 migrations on MySQL/MariaDB with existing ORD-01 data (ORD-02e): up, backfill, non-destructive rollback,
 * up again. Run with DB_CONNECTION=mysql; skipped on SQLite.
 */
class OrderMigrationsMariaDbTest extends TestCase
{
    /** ORD-02a … ORD-02e and contract r4.2, newest last. */
    private const ORD02 = [
        '2026_10_08_100001_add_admin_access_controls', '2026_10_08_200001_add_submission_token_to_online_orders',
        '2026_10_08_300001_add_order_state_dimensions_to_online_orders', '2026_10_09_000001_add_order_cutover_and_money_ledger',
        '2026_10_09_100001_add_order_issues_and_v2_operations', '2026_10_09_200001_create_customers_and_saved_addresses',
        '2026_10_09_300001_add_keep_adjustments_and_change_requests', '2026_10_09_400001_create_order_api_tables',
        '2026_10_09_500001_add_delivery_to_online_order_events', '2026_10_10_000001_add_payment_preference_to_online_orders',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Migration rehearsal runs on MySQL/MariaDB only.');
        }
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        parent::tearDown();
    }

    public function test_ord02_migrations_backfill_ord01_data_and_roll_back_without_losing_it(): void
    {
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($file) => basename($file, '.php'))->sort()->values();
        $this->assertSame(self::ORD02, $files->slice(-count(self::ORD02))->values()->all(), 'ORD-02 migrations are the newest ones');

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('migrate:rollback', ['--step' => count(self::ORD02), '--force' => true]);
        $this->assertFalse(Schema::hasColumn('online_orders', 'lifecycle'));
        $this->assertFalse(Schema::hasColumn('users', 'username'));

        $ids = $this->seedOrd01();
        $before = $this->ord01Snapshot();

        Artisan::call('migrate', ['--force' => true]);
        $this->assertBackfilled($ids);
        $this->assertNoImplicitTimestampUpdates();

        Artisan::call('migrate:rollback', ['--step' => count(self::ORD02), '--force' => true]);
        $this->assertSame($before, $this->ord01Snapshot(), 'Rollback keeps every ORD-01 row and value');
        foreach (['online_order_payments', 'online_order_issues', 'customers', 'online_order_claims', 'integration_outbox', 'integration_idempotency_keys', 'online_order_costs'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }

        Artisan::call('migrate', ['--force' => true]);
        $this->assertBackfilled($ids);
        $this->assertSame($before, $this->ord01Snapshot(), 'Re-running the migrations changes no ORD-01 value');
    }

    /**
     * MariaDB with explicit_defaults_for_timestamp=OFF silently gives the first NOT NULL TIMESTAMP column
     * ON UPDATE CURRENT_TIMESTAMP (found here: customer_link_expires_at was reset on every order update).
     */
    private function assertNoImplicitTimestampUpdates(): void
    {
        $tables = ['online_orders', 'online_order_events', 'online_order_payments', 'online_order_claims', 'online_order_costs',
            'integration_idempotency_keys', 'integration_outbox', 'user_admin_changes', 'online_order_issues', 'customers', 'customer_addresses'];
        $auto = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::getDatabaseName())->whereIn('TABLE_NAME', $tables)
            ->where('EXTRA', 'like', '%on update%')->pluck('COLUMN_NAME', 'TABLE_NAME')->all();
        $this->assertSame([], $auto, 'No column may change itself on UPDATE');

        $id = DB::table('online_orders')->value('id');
        $before = DB::table('online_orders')->where('id', $id)->value('customer_link_expires_at');
        sleep(1);
        DB::table('online_orders')->where('id', $id)->update(['customer_note' => 'cek timestamp']);
        $this->assertSame($before, DB::table('online_orders')->where('id', $id)->value('customer_link_expires_at'));
    }

    private function seedOrd01(): array
    {
        $admin = DB::table('users')->insertGetId(['name' => 'Admin Lama', 'email' => 'lama@example.test', 'password' => bcrypt('x'), 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $order = function (string $stage, string $fulfillment, ?string $courier) use ($admin) {
            $id = DB::table('online_orders')->insertGetId([
                'customer_token_hash' => hash('sha256', Str::random(40)), 'customer_token_encrypted' => 'x',
                'staff_token_hash' => hash('sha256', Str::random(40)), 'staff_token_encrypted' => 'x', 'customer_link_expires_at' => now()->addDays(7),
                'stage' => $stage, 'customer_name' => 'Legacy '.$stage, 'customer_phone' => '081200000000', 'fulfillment' => $fulfillment,
                'packaging' => 'paperbag', 'courier' => $courier, 'payment_method' => 'transfer', 'created_by' => $admin,
                'closed_at' => in_array($stage, ['cancelled', 'completed'], true) ? now() : null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('online_orders')->where('id', $id)->update(['code' => 'QAM-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)]);
            DB::table('online_order_items')->insert(['online_order_id' => $id, 'product_id' => 1, 'variant_id' => 1, 'brand_name' => 'B', 'product_name' => 'P',
                'volume' => 50, 'unit_price' => 150000, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        };
        $event = fn (int $order, string $kind, string $stage, string $at) => DB::table('online_order_events')->insert(['online_order_id' => $order, 'kind' => $kind, 'stage' => $stage, 'actor_type' => 'admin', 'actor_user_id' => $admin, 'created_at' => $at]);

        $shipped = $order('shipped', 'local_delivery', 'maxim');
        $event($shipped, 'advance', 'paid', '2026-10-01 01:00:00');
        $event($shipped, 'advance', 'shipped', '2026-10-01 02:00:00');
        $cancelled = $order('cancelled', 'pickup', null);
        $event($cancelled, 'advance', 'paid', '2026-10-02 01:00:00');
        $event($cancelled, 'cancel', 'paid', '2026-10-02 03:00:00');
        $waiting = $order('awaiting_customer', 'local_delivery', null);

        return compact('shipped', 'cancelled', 'waiting');
    }

    private function assertBackfilled(array $ids): void
    {
        $rows = DB::table('online_orders')->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $row->public_id);
            $this->assertSame('legacy', $row->state_model);
        }
        $shipped = $rows[$ids['shipped']];
        $this->assertSame(['active', 'paid', 'packed', 'handed_over', 'courier', 'maxim', '2026-10-01 02:00:00'],
            [$shipped->lifecycle, $shipped->payment_status, $shipped->preparation_status, $shipped->handover_status, $shipped->handed_to, $shipped->courier_provider, $shipped->handed_over_at]);
        $cancelled = $rows[$ids['cancelled']];
        $this->assertSame(['cancelled', 'paid', 'needs_reconciliation'], [$cancelled->lifecycle, $cancelled->payment_status, $cancelled->refund_status],
            'A legacy cancel after payment is flagged for reconciliation, never assumed to owe a refund');
        $this->assertSame(['awaiting_customer', 'unpaid'], [$rows[$ids['waiting']]->lifecycle, $rows[$ids['waiting']]->payment_status]);
        $this->assertSame(0, DB::table('online_order_items')->whereNull('line_id')->count());
        $this->assertSame(3, DB::table('online_order_items')->distinct()->count('line_id'));
    }

    private function ord01Snapshot(): array
    {
        return [
            DB::table('online_orders')->orderBy('id')->get(['id', 'code', 'stage', 'customer_name', 'customer_phone', 'fulfillment', 'courier', 'payment_method', 'closed_at'])->map(fn ($r) => (array) $r)->all(),
            DB::table('online_order_items')->orderBy('id')->get(['id', 'online_order_id', 'product_name', 'unit_price', 'quantity'])->map(fn ($r) => (array) $r)->all(),
            DB::table('online_order_events')->orderBy('id')->get(['id', 'online_order_id', 'kind', 'stage', 'created_at'])->map(fn ($r) => (array) $r)->all(),
            DB::table('users')->orderBy('id')->get(['id', 'email', 'role'])->map(fn ($r) => (array) $r)->all(),
        ];
    }
}
