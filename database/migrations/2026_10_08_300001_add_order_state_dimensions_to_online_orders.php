<?php

use App\Support\OnlineOrderLegacyState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * ORD-02c slice 1: separate payment, preparation, courier, J&T, handover and delivery dimensions plus a
     * public ULID. Additive; `stage` stays and keeps driving the ORD-01 screens until ORD-02d switches over.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->unique()->after('code');
            $table->string('source', 16)->default('whatsapp')->after('public_id');
            $table->string('lifecycle', 20)->nullable()->index()->after('stage');
            $table->string('payment_status', 16)->nullable()->index()->after('lifecycle');
            $table->string('payment_confirmation_source', 20)->nullable()->after('payment_method');
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirmation_source');
            $table->unsignedBigInteger('payment_confirmed_by')->nullable()->after('payment_confirmed_at');
            $table->string('preparation_status', 16)->nullable()->after('payment_status');
            $table->string('courier_booking_responsibility', 10)->nullable()->after('courier_booked_by');
            $table->string('courier_provider', 20)->nullable()->after('courier_booking_responsibility');
            $table->string('courier_status', 16)->nullable()->after('courier_provider');
            $table->string('courier_reference', 80)->nullable()->after('courier_status');
            $table->string('jnt_status', 20)->nullable()->after('tracking_number');
            $table->timestamp('jnt_pickup_requested_at')->nullable()->after('jnt_status');
            $table->timestamp('jnt_picked_up_at')->nullable()->after('jnt_pickup_requested_at');
            $table->string('handover_status', 16)->nullable()->after('jnt_picked_up_at');
            $table->string('handed_to', 20)->nullable()->after('handover_status');
            $table->timestamp('handed_over_at')->nullable()->after('handed_to');
            $table->string('delivery_status', 16)->nullable()->after('handed_over_at');
            $table->timestamp('delivered_at')->nullable()->after('delivery_status');
        });

        $this->backfill();
    }

    /** Deterministic backfill from ORD-01 stage + events. Idempotent: only rows without the new state are touched. */
    public function backfill(): void
    {
        DB::table('online_orders')->whereNull('lifecycle')->orderBy('id')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $events = DB::table('online_order_events')->where('online_order_id', $order->id)->orderBy('id')->get(['kind', 'stage', 'created_at']);
                $reached = fn (string $stage) => Carbon::parse(
                    $events->last(fn ($event) => $event->kind === 'advance' && $event->stage === $stage)?->created_at ?? $order->updated_at
                );
                $previous = $order->stage === 'cancelled' ? $events->last(fn ($event) => $event->kind === 'cancel')?->stage : null;

                DB::table('online_orders')->where('id', $order->id)->update(
                    ['public_id' => $order->public_id ?? (string) Str::ulid()]
                    + OnlineOrderLegacyState::dimensions((array) $order + ['previous_stage' => $previous], $reached)
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropIndex(['lifecycle']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn([
                'public_id', 'source', 'lifecycle', 'payment_status', 'payment_confirmation_source', 'payment_confirmed_at',
                'payment_confirmed_by', 'preparation_status', 'courier_booking_responsibility', 'courier_provider', 'courier_status',
                'courier_reference', 'jnt_status', 'jnt_pickup_requested_at', 'jnt_picked_up_at', 'handover_status', 'handed_to',
                'handed_over_at', 'delivery_status', 'delivered_at',
            ]);
        });
    }
};
