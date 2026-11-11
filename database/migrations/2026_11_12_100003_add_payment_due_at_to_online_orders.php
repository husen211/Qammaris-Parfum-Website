<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-15: when an unpaid order is cancelled automatically (48 hours after it could first be paid). Null until the
     * order can be paid, and for every existing order until the scheduler sets it.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->timestamp('payment_due_at')->nullable()->after('courier_booking')->index();
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_due_at']);
            $table->dropColumn('payment_due_at');
        });
    }
};
