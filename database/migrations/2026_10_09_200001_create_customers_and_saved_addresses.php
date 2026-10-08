<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-02c slice 4: repeat customers and their confirmed addresses. Orders keep their own copy of the
     * recipient data; a saved address is only a source to copy from, chosen explicitly.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Normalized (62…). Indexed, not unique: family members may share a number.
            $table->string('phone', 15)->index();
            $table->string('note', 300)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('label', 40);
            $table->string('type', 12); // local | intercity
            $table->string('address', 500);
            $table->string('postcode', 5)->nullable();
            $table->string('location_url', 500)->nullable();
            $table->unsignedBigInteger('confirmed_by');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'archived_at']);
        });

        Schema::table('online_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->index()->after('created_by');
            $table->unsignedBigInteger('customer_address_id')->nullable()->after('customer_id');
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
            $table->dropIndex(['customer_id']);
            $table->dropColumn(['customer_id', 'customer_address_id']);
        });
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
