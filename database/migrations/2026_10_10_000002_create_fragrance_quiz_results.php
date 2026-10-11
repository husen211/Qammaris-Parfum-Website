<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragrance_quiz_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('browser_hash', 64)->index();
            $table->string('question_version', 60);
            $table->string('engine_version', 60)->index();
            $table->char('engine_fingerprint', 64);
            $table->json('answers');
            $table->json('recommendations');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('fragrance_quiz_feedback', function (Blueprint $table) {
            $table->id();
            $table->uuid('result_id')->unique();
            $table->string('overall', 20);
            $table->json('products');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fragrance_quiz_feedback');
        Schema::dropIfExists('fragrance_quiz_results');
    }
};
