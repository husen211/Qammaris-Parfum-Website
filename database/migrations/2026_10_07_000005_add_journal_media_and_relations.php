<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->restrictOnDelete();
            $table->string('disk', 64);
            $table->string('path', 2048);
            $table->string('alt')->default('');
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('license')->nullable();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('checksum', 64);
            $table->string('crop', 16)->default('original');
            $table->unsignedTinyInteger('focal_x')->default(50);
            $table->unsignedTinyInteger('focal_y')->default(50);
            $table->json('variants')->nullable();
            $table->string('processing_warning')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('featured_media_id')->nullable()->constrained('blog_media')->restrictOnDelete();
            $table->json('related_product_ids')->nullable();
            $table->json('related_article_ids')->nullable();
            $table->json('faqs')->nullable();
            $table->json('references')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_media_id');
            $table->dropColumn(['related_product_ids', 'related_article_ids', 'faqs', 'references']);
        });
        Schema::dropIfExists('blog_media');
    }
};
