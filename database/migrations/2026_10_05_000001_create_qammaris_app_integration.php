<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qammaris_app_sync_states', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('checkpoint')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
        });
        DB::table('qammaris_app_sync_states')->insert(['id' => 'products', 'checkpoint' => 0]);

        Schema::create('qammaris_app_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('revision');
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::create('qammaris_app_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id');
            $table->unsignedBigInteger('revision');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor')->default('qammaris_app');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
            $table->unique(['external_id', 'revision', 'product_id'], 'qammaris_app_change_identity');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('qammaris_app_hidden')->default(false);
            $table->date('availability_restock_eta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['qammaris_app_hidden', 'availability_restock_eta']);
        });
        Schema::dropIfExists('qammaris_app_changes');
        Schema::dropIfExists('qammaris_app_products');
        Schema::dropIfExists('qammaris_app_sync_states');
    }
};
