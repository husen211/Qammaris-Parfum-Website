<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ORD-09: browsers (store laptop, phones) that asked for admin push notifications. One row per browser endpoint. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // Unique lookup key; push endpoints are long URLs.
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('user_agent', 160)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_push_subscriptions');
    }
};
