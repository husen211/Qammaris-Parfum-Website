<?php

namespace Tests\Feature;

use App\Models\OnlineOrderCost;
use App\Support\OrderApi\OrderApiSchema;
use App\Support\OrderApi\OrderSerializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsV2Orders;
use Tests\TestCase;

/**
 * Release check (2026-10-17): the ORD-08/09 migrations meet costs that Qammaris App already reported in production.
 * Those rows must become origin "app" with no advancer and keep serialising exactly as before.
 */
class OrderCostMigrationTest extends TestCase
{
    use BuildsV2Orders, RefreshDatabase;

    private const NEW_MIGRATIONS = [
        '2026_10_17_200001_create_admin_push_subscriptions_table',
        '2026_10_17_100002_add_advanced_by_app_user_id_to_online_order_costs',
        '2026_10_17_100001_add_website_origin_to_online_order_costs',
    ];

    public function test_existing_app_costs_survive_the_new_migrations(): void
    {
        $order = $this->paidV2Order();
        Artisan::call('migrate:rollback', ['--path' => array_map(fn ($m) => database_path("migrations/{$m}.php"), self::NEW_MIGRATIONS), '--realpath' => true, '--force' => true]);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('online_order_costs', 'origin'));

        // A cost as the released code stores it today (App-reported, staff advance awaiting proof).
        DB::table('online_order_costs')->insert([
            'online_order_id' => $order->id, 'expense_ref' => 'exp_prod_existing_0001', 'kind' => 'actual_shipping', 'status' => 'active',
            'amount' => '15000.00', 'funding' => json_encode([['source' => 'staff_advance', 'amount' => 15000]]), 'reimbursement_status' => 'awaiting_proof',
            'reimbursement_amount' => '15000.00', 'proof' => 'pending', 'waiver' => null, 'reimbursement_updated_at' => now(), 'source_version' => 2,
            'reported_by_app_user_id' => '665f0c2a9b1e4a0012ab34cd', 'reported_by_name' => 'Andi', 'reported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Artisan::call('migrate', ['--force' => true]);
        $cost = OnlineOrderCost::sole();
        $this->assertSame(['app', null, null, null], [$cost->origin, $cost->advanced_by_name, $cost->advanced_by_app_user_id, $cost->recorded_by_user_id]);
        $this->assertSame(['15000.00', 2, 'awaiting_proof'], [$cost->amount, $cost->source_version, $cost->reimbursement_status]);

        $wire = OrderSerializer::order($order->fresh());
        $this->assertSame([], OrderApiSchema::errors(json_decode(json_encode($wire)), 'Order'));
        $this->assertSame(['app', 'Andi', null], [$wire['costs'][0]['origin'], $wire['costs'][0]['reported_by']['display_name'], $wire['costs'][0]['advanced_by']]);
    }
}
