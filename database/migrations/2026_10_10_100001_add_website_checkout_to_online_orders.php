<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-04: website cart checkout creates guest orders (no Website account, so no creator), idempotent per
     * checkout key. Phase-2 extension points stay empty until a rule fills them: area detail, shipping estimate
     * and its source, and weights (unknown weight is null, never a default).
     */
    public function up(): void
    {
        Schema::table('online_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->uuid('checkout_key')->nullable()->unique()->after('submission_token');
            $table->string('district', 80)->nullable()->after('postcode');
            $table->string('subdistrict', 80)->nullable()->after('district');
            $table->decimal('shipping_estimate', 10, 2)->nullable()->after('shipping_fee');
            $table->string('shipping_estimate_source', 16)->nullable()->after('shipping_estimate');
        });
        Schema::table('online_order_items', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable()->after('quantity');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('weight_grams')->nullable()->after('volume');
        });
    }

    public function down(): void
    {
        // Guest orders have no creator; restoring NOT NULL would need invented creators, so rollback stops instead.
        if (DB::table('online_orders')->whereNull('created_by')->exists()) {
            throw new RuntimeException('Pesanan website (tanpa pembuat) sudah ada; rollback ORD-04 dihentikan agar data tidak diubah.');
        }
        foreach (['online_orders', 'online_order_items', 'product_variants'] as $table) {
            // MariaDB instant DROP COLUMN keeps hidden metadata; rebuild first so repeated rollbacks stay possible.
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE {$table} FORCE");
            }
        }
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('weight_grams'));
        Schema::table('online_order_items', fn (Blueprint $table) => $table->dropColumn('weight_grams'));
        Schema::table('online_orders', function (Blueprint $table) {
            $table->dropUnique(['checkout_key']);
            $table->dropColumn(['checkout_key', 'district', 'subdistrict', 'shipping_estimate', 'shipping_estimate_source']);
        });
        Schema::table('online_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
