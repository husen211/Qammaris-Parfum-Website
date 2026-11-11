<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-13: the courier option priced at checkout (courier, Biteship price, store discount, charged amount,
     * destination pin or area, weight). Null for every existing order and whenever staff confirm ongkir.
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->json('shipping_quote')->nullable()->after('shipping_estimate_source');
        });
    }

    public function down(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropColumn('shipping_quote');
        });
    }
};
