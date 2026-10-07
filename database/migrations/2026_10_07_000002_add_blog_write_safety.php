<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('content_updated_at')->nullable();
            $table->string('featured_image_disk', 64)->nullable();
        });

        Schema::create('blog_post_changes', function (Blueprint $table) {
            $table->id();
            // Historical attribution must survive a separately approved deletion.
            $table->unsignedBigInteger('blog_post_id');
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 32);
            $table->unsignedBigInteger('revision');
            $table->json('changed_fields');
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at');
            $table->index(['blog_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_changes');
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['revision', 'archived_at', 'content_updated_at', 'featured_image_disk']);
        });
    }
};
