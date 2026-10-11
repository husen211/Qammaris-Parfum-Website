<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-04: paperbag charge (Rp4.000 per order with "Pakai paperbag") as a separate part of the customer total,
     * snapshotted when the packaging is chosen. Existing orders keep null (= no charge); nothing is backfilled.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->decimal('packaging_fee', 10, 2)->nullable()->after('packaging');
        });
    }

    public function down(): void
    {
        // MariaDB instant DROP COLUMN keeps hidden metadata; rebuild first so repeated rollbacks stay possible.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE online_orders FORCE');
        }
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn('packaging_fee');
        });
    }
};
