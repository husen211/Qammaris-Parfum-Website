<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-02b: one create-form submission makes at most one order, even after a double tap or a retry
     * on a flaky shop phone connection. Nullable, so existing orders are untouched.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->after('created_by');
            $table->unique(['created_by', 'submission_token']);
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
            $table->dropUnique(['created_by', 'submission_token']);
            $table->dropColumn('submission_token');
        });
    }
};
