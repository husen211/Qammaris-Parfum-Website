<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->text('canonical_url')->nullable();
            $table->boolean('seo_indexable')->default(true);
            $table->boolean('seo_followable')->default(true);
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->text('og_image_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', fn (Blueprint $table) => $table->dropColumn([
            'canonical_url', 'seo_indexable', 'seo_followable', 'og_title', 'og_description', 'og_image_url',
        ]));
    }
};
