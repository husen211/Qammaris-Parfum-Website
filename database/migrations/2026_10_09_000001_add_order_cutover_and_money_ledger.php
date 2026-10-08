<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-02c slice 2: legacy/V2 cutover marker, refund decision and an append-only payment/refund ledger.
     * Corrects slice 1: a cancelled-after-payment ORD-01 order is no longer assumed to owe a refund.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            // Which model drives state. Decided when the order is created; never switched afterwards.
            $table->string('state_model', 8)->default('legacy')->index()->after('source');
            $table->string('refund_status', 24)->nullable()->index()->after('payment_status');
            $table->decimal('refund_due_amount', 12, 2)->nullable()->after('refund_status');
            $table->text('refund_reason')->nullable()->after('refund_due_amount');
            $table->unsignedBigInteger('refund_decided_by')->nullable()->after('refund_reason');
            $table->timestamp('refund_decided_at')->nullable()->after('refund_decided_by');
        });

        Schema::create('online_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->string('type', 8); // payment | refund
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->nullable();
            $table->string('confirmation_source', 20)->nullable();
            $table->string('reference', 80)->nullable();
            // recorded = entered when it happened; reconciled = confirmed later by Super Admin; reversal = cancels `reverses_id`
            $table->string('basis', 12)->default('recorded');
            $table->unsignedBigInteger('reverses_id')->nullable()->unique();
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['online_order_id', 'created_at']);
        });

        $this->correctLegacyRefunds();
    }

    /**
     * Idempotent. ORD-01 kept no refund history. A cancelled order that had been paid (slice 1 wrongly wrote refund_pending)
     * needs a Super Admin reconciliation instead of an assumed debt.
     */
    public function correctLegacyRefunds(): void
    {
        DB::table('online_orders')
            ->where('state_model', 'legacy')->where('lifecycle', 'cancelled')->whereIn('payment_status', ['paid', 'refund_pending'])
            ->whereNull('refund_status')
            ->update(['payment_status' => 'paid', 'refund_status' => 'needs_reconciliation']);
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_payments');
        // MariaDB instant ADD/DROP COLUMN keeps dropped columns as hidden metadata; a rollback after an earlier
        // rollback/migrate cycle then fails the row-size check. A rebuild (data-preserving) clears it first.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE online_orders FORCE');
        }
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropIndex(['state_model']);
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['state_model', 'refund_status', 'refund_due_amount', 'refund_reason', 'refund_decided_by', 'refund_decided_at']);
        });
    }
};
