<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ORD-08: the advancing employee is chosen from the Qammaris App's own employee list and linked by App account ID. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_order_costs', function (Blueprint $table) {
            $table->string('advanced_by_app_user_id', 24)->nullable()->after('advanced_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('online_order_costs', function (Blueprint $table) {
            $table->dropColumn('advanced_by_app_user_id');
        });
    }
};
