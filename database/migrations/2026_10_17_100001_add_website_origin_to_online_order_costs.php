<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORD-08 (Owner 2026-10-17): Staff Order records the courier cost and whose money paid it on the Website. Such a cost
 * is website-origin (the Website owns amount and funding; the App owns the reimbursement). Existing rows stay "app".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_order_costs', function (Blueprint $table) {
            $table->string('origin', 10)->default('app')->after('expense_ref');
            // Employee who advanced the money, matched by name to their Qammaris App account.
            $table->string('advanced_by_name', 60)->nullable()->after('funding');
            $table->foreignId('recorded_by_user_id')->nullable()->after('reported_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('online_order_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by_user_id');
            $table->dropColumn(['origin', 'advanced_by_name']);
        });
    }
};
