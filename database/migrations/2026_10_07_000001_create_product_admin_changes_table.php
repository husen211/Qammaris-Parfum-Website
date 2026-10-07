<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_admin_changes', function (Blueprint $table) {
            $table->id();
            // Historical IDs survive any separately approved deletion of a product or user.
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('image_id')->nullable();
            $table->string('action', 32);
            $table->json('changed_fields');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_admin_changes');
    }
};
