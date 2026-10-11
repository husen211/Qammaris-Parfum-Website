<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_automation_actors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreignId('automation_actor_id')->nullable()->constrained('blog_automation_actors')->restrictOnDelete();
        });
        Schema::create('blog_automation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('blog_automation_actors')->restrictOnDelete();
            $table->string('scope', 100);
            $table->char('key_hash', 64);
            $table->char('payload_hash', 64);
            $table->string('state', 16)->default('pending');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedBigInteger('revision')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'scope', 'key_hash'], 'blog_request_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_automation_requests');
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('automation_actor_id');
        });
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('blog_automation_actors');
    }
};
