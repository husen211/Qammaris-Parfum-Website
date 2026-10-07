<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        foreach (['Tips', 'Review', 'Panduan', 'Berita'] as $name) {
            DB::table('blog_categories')->insert(['name' => $name, 'slug' => Str::slug($name), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->restrictOnDelete();
            $table->text('subtitle')->nullable();
            $table->string('featured_image_alt')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title')->nullable();
        });
        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('blog_post_id')->constrained('blog_posts')->restrictOnDelete();
            $table->foreignId('blog_tag_id')->constrained('blog_tags')->restrictOnDelete();
            $table->primary(['blog_post_id', 'blog_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['subtitle', 'featured_image_alt', 'is_featured', 'seo_title']);
        });
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_categories');
    }
};
