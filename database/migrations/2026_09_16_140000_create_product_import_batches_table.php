<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_filename');
            $table->unsignedBigInteger('source_size');
            $table->char('source_fingerprint', 64);
            $table->string('contract_version', 20);
            $table->char('catalog_state_fingerprint', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 30)->default('previewed');
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('valid_rows');
            $table->unsignedInteger('review_rows');
            $table->unsignedInteger('error_rows');
            $table->json('skipped_blank_rows')->nullable();
            $table->timestamps();

            $table->index(['source_fingerprint', 'created_at']);
        });

        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('product_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('status', 20);
            $table->string('candidate_action', 20);
            $table->string('provider')->nullable();
            $table->string('external_product_id')->nullable();
            $table->foreignId('matched_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('normalized_data');
            $table->json('issues');
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(['batch_id', 'line_number']);
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_import_batches');
    }
};
