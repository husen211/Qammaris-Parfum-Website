<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-14: the instant courier booked through Biteship (booking state, Biteship order id and status, price,
     * driver, tracking link, who booked). Null for every existing order and for couriers booked by hand.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->json('courier_booking')->nullable()->after('shipping_quote');
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn('courier_booking');
        });
    }
};
