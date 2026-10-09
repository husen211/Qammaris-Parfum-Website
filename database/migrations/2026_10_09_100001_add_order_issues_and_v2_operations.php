<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** ORD-02c slice 3: order issues, packing confirmation, stable line/event IDs and App actors on events. */
    public function up(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->char('line_id', 26)->nullable()->unique()->after('id');
        });
        Schema::table('online_orders', function (Blueprint $table) {
            // Confirmed packed quantities per line_id, checked against the order at the expected revision.
            $table->json('packed_items')->nullable()->after('preparation_status');
            $table->timestamp('courier_requested_at')->nullable()->after('courier_reference');
            $table->string('delivery_confirmed_by', 10)->nullable()->after('delivered_at');
        });
        Schema::table('online_order_events', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->unique()->after('id');
            $table->string('actor_app_user_id', 24)->nullable()->after('actor_user_id');
            $table->string('actor_display_name', 60)->nullable()->after('actor_app_user_id');
        });
        Schema::create('online_order_issues', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->string('note', 500);
            $table->char('line_id', 26)->nullable();
            $table->unsignedSmallInteger('reported_quantity')->nullable();
            $table->string('status', 10)->default('open');
            $table->unsignedBigInteger('opened_by_user_id')->nullable();
            $table->string('opened_by_app_user_id', 24)->nullable();
            $table->string('opened_by_name', 60);
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->string('resolved_by_app_user_id', 24)->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['online_order_id', 'status']);
        });

        DB::table('online_order_items')->whereNull('line_id')->orderBy('id')->chunkById(500, function ($items) {
            foreach ($items as $item) {
                DB::table('online_order_items')->where('id', $item->id)->update(['line_id' => (string) Str::ulid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_issues');
        Schema::table('online_order_events', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'actor_app_user_id', 'actor_display_name']);
        });
        // MariaDB instant ADD/DROP COLUMN keeps dropped columns as hidden metadata; a rollback after an earlier
        // rollback/migrate cycle then fails the row-size check. A rebuild (data-preserving) clears it first.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE online_orders FORCE');
        }
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn(['packed_items', 'courier_requested_at', 'delivery_confirmed_by']);
        });
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->dropUnique(['line_id']);
            $table->dropColumn('line_id');
        });
    }
};
