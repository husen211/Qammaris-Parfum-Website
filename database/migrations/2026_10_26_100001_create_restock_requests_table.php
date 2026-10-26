<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WAIT-01: "Kabari saya saat ready" requests from sold-out product pages. Each row is also its own outbox row for the
 * signed `waitlist.requested` event to Qammaris App; contact data is cleared once the App has accepted it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_requests', function (Blueprint $table) {
            $table->id();
            // data.request_id and event_id of the App event; both stay fixed across retries.
            $table->char('request_id', 26)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('customer_name', 60)->nullable();
            $table->string('whatsapp', 20)->nullable();
            // Keyed hash of the normalized number: blocks repeat requests after the contact data is cleared.
            $table->char('whatsapp_hash', 64);
            // The exact raw JSON body; every retry sends these bytes with a fresh timestamp/signature.
            $table->text('body')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('first_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'whatsapp_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_requests');
    }
};
