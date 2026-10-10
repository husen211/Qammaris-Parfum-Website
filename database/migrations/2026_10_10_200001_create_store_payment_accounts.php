<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-04: official bank accounts for payment instructions. Super Admin adds them; an account is used only after a
     * separate verification (re-typed number). Accounts are never edited or deleted: deactivate and add a new one.
     */
    public function up(): void
    {
        Schema::create('store_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 40);
            $table->string('account_number', 30);
            $table->string('account_holder', 80);
            $table->unsignedBigInteger('created_by');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('deactivated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payment_accounts');
    }
};
