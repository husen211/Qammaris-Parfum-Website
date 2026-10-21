<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORD-05 (additive): customers are saved automatically, also from a customer's own link where no admin acts, so
 * the creator/confirmer may be empty; a Super Admin can anonymize a customer on request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->timestamp('anonymized_at')->nullable()->after('note');
            $table->unsignedBigInteger('anonymized_by')->nullable()->after('anonymized_at');
        });
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('confirmed_by')->nullable()->change();
        });
    }

    /** Drops only the added columns; making created_by/confirmed_by required again could fail on rows saved since. */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['anonymized_at', 'anonymized_by']);
        });
    }
};
