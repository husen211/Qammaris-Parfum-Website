<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Contract r4.2: transport identity (X-Qammaris-Delivery) kept apart from the actor on each App event. */
    public function up(): void
    {
        Schema::table('online_order_events', function (Blueprint $table) {
            $table->string('delivery', 8)->nullable()->after('request_id');
        });
    }

    public function down(): void
    {
        // MariaDB instant DROP COLUMN keeps hidden metadata; rebuild first so repeated rollbacks stay possible.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE online_order_events FORCE');
        }
        Schema::table('online_order_events', function (Blueprint $table) {
            $table->dropColumn('delivery');
        });
    }
};
