<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ORD-02e: private Order API v1 — claims, idempotency, webhook outbox, App costs, audit columns, private QR. */
    public function up(): void
    {
        // One active holder per (order, task), enforced by the unique key; release deletes the row (audit keeps the event).
        Schema::create('online_order_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->string('task', 16);
            $table->string('holder_app_user_id', 24);
            $table->string('holder_display_name', 60);
            $table->timestamp('claimed_at')->useCurrent();
            $table->unique(['online_order_id', 'task']);
        });

        Schema::create('integration_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('client', 64);
            $table->string('idempotency_key', 128);
            $table->string('method', 8);
            $table->string('path', 255);
            $table->char('payload_hash', 64);
            $table->unsignedSmallInteger('status_code');
            $table->longText('response_body');
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->useCurrent()->index();
            $table->unique(['client', 'idempotency_key']);
        });

        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->char('event_id', 26)->unique();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('type', 32);
            // The exact raw JSON body; every retry sends these bytes with a fresh timestamp/signature.
            $table->text('body');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('first_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('online_order_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            // Real App expense ID; unique across all orders (one expense links to one order).
            $table->string('expense_ref', 64)->unique();
            $table->string('kind', 20);
            $table->string('status', 10);
            $table->decimal('amount', 12, 2);
            $table->json('funding');
            $table->string('reimbursement_status', 20);
            $table->decimal('reimbursement_amount', 12, 2);
            $table->string('proof', 10);
            $table->json('waiver')->nullable();
            $table->timestamp('reimbursement_updated_at')->useCurrent();
            $table->unsignedInteger('source_version');
            $table->string('reported_by_app_user_id', 24);
            $table->string('reported_by_name', 60);
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamps();
        });

        Schema::table('online_order_events', function (Blueprint $table) {
            $table->string('source', 10)->nullable()->after('actor_type');
            $table->string('idempotency_key', 128)->nullable()->after('note');
            // X-Request-Id from the App, to correlate failures across both systems.
            $table->string('request_id', 64)->nullable()->after('idempotency_key');
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->string('jnt_qr_path', 255)->nullable()->after('jnt_picked_up_at');
            $table->string('jnt_qr_mime', 20)->nullable()->after('jnt_qr_path');
        });
    }

    public function down(): void
    {
        // MariaDB instant ADD/DROP COLUMN keeps dropped columns as hidden metadata; a rollback after an earlier
        // rollback/migrate cycle then fails the row-size check. A rebuild (data-preserving) clears it first.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE online_orders FORCE');
        }
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn(['jnt_qr_path', 'jnt_qr_mime']);
        });
        Schema::table('online_order_events', function (Blueprint $table) {
            $table->dropColumn(['source', 'idempotency_key', 'request_id']);
        });
        Schema::dropIfExists('online_order_costs');
        Schema::dropIfExists('integration_outbox');
        Schema::dropIfExists('integration_idempotency_keys');
        Schema::dropIfExists('online_order_claims');
    }
};
