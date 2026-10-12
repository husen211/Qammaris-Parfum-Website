<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();
            // Hashes locate links; encrypted copies let admins re-copy them. Raw tokens are never stored in plain text.
            $table->char('customer_token_hash', 64)->unique();
            $table->text('customer_token_encrypted');
            $table->char('staff_token_hash', 64)->unique();
            $table->text('staff_token_encrypted');
            $table->timestamp('customer_link_expires_at')->useCurrent();
            $table->string('stage', 24)->index();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('fulfillment', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('postcode', 5)->nullable();
            $table->text('location_url')->nullable();
            $table->string('packaging', 20)->nullable();
            $table->text('customer_note')->nullable();
            $table->string('courier', 20)->nullable();
            $table->string('courier_booked_by', 10)->default('admin');
            $table->string('tracking_number', 40)->nullable();
            $table->decimal('shipping_fee', 10, 2)->nullable();
            $table->string('shipping_payer', 24)->nullable();
            $table->string('driver_funding', 24)->nullable();
            $table->decimal('staff_advance_amount', 10, 2)->nullable();
            $table->string('staff_advance_by', 40)->nullable();
            $table->timestamp('staff_reimbursed_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->boolean('recorded_in_majoo')->default(false);
            $table->text('staff_note')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->unsignedInteger('revision')->default(1);
            // Historical IDs survive any separately approved deletion of a user.
            $table->unsignedBigInteger('created_by');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('online_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            // Catalog IDs are historical references; the snapshot is the agreed order line.
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id');
            $table->string('brand_name');
            $table->string('product_name');
            $table->unsignedInteger('volume')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });

        Schema::create('online_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('stage', 24)->nullable();
            $table->string('actor_type', 10);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('staff_name', 40)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['online_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_events');
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
    }
};
