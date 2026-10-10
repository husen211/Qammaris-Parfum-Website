<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fragrance_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->string('source_fingerprint', 64);
            $table->string('parser_fingerprint', 64);
            $table->string('parser_version', 64);
            $table->json('derived');
            $table->json('overrides')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('fragrance_profile_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fragrance_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('operation', 24);
            $table->json('evidence');
            $table->timestamps();
            $table->unique(['fragrance_profile_id', 'revision'], 'fragrance_revision_unique');
        });
    }

    public function down(): void
    {
        // Operational rollback uses the disabled flag and retains profiles/revisions.
        Schema::dropIfExists('fragrance_profile_revisions');
        Schema::dropIfExists('fragrance_profiles');
    }
};
