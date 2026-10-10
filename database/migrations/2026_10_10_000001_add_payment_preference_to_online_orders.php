<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ORD-03: the payment method the customer prefers (Website-internal; not part of API v1, never marks paid). */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->string('payment_preference', 10)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        // MariaDB instant DROP COLUMN keeps hidden metadata; rebuild first so repeated rollbacks stay possible.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE online_orders FORCE');
        }
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn('payment_preference');
        });
    }
};
