<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ORD-04 "Pesanan Saya" without login: a browser holds only a random key in a secure cookie; the server keeps the
     * key's hash and which orders that browser opened. No token, recipient data or account is stored here.
     */
    public function up(): void
    {
        Schema::create('guest_order_histories', function (Blueprint $table) {
            $table->id();
            $table->char('key_hash', 64)->unique();
            $table->timestamp('expires_at')->useCurrent()->index();
            $table->timestamps();
        });
        Schema::create('guest_order_history_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_order_history_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('online_order_id');
            $table->timestamp('opened_at')->useCurrent();
            $table->unique(['guest_order_history_id', 'online_order_id'], 'guest_history_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_order_history_entries');
        Schema::dropIfExists('guest_order_histories');
    }
};
