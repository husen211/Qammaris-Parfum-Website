<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ORD-02c slice 5: keep (D9), price adjustments with Super Admin approval, customer change requests. */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->string('keep_status', 10)->nullable()->after('delivery_confirmed_by'); // active | converted | released
            $table->timestamp('keep_until')->nullable()->after('keep_status');
            $table->timestamp('keep_stock_confirmed_at')->nullable()->after('keep_until');
            $table->unsignedBigInteger('keep_stock_confirmed_by')->nullable()->after('keep_stock_confirmed_at');
        });

        Schema::create('online_order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2); // signed: negative = discount
            $table->string('reason', 300);
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 300)->nullable();
            $table->timestamps();
            $table->index(['online_order_id', 'status']);
        });

        Schema::create('online_order_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->json('changes'); // requested field => value (recipient fields only)
            $table->string('source', 10); // customer | admin
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 300)->nullable();
            $table->timestamps();
            $table->index(['online_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_change_requests');
        Schema::dropIfExists('online_order_adjustments');
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn(['keep_status', 'keep_until', 'keep_stock_confirmed_at', 'keep_stock_confirmed_by']);
        });
    }
};
