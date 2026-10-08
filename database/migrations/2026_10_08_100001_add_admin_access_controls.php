<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORD-02a: roles, account status and account audit. Additive only: existing users keep their role,
 * password and email; nobody is promoted here (Owner becomes Super Admin via an explicit command).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->unique()->after('name');
            $table->boolean('is_active')->default(true)->after('role');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            // Bumped on deactivation, role change or password reset; older sessions stop being accepted.
            $table->unsignedInteger('auth_version')->default(1)->after('must_change_password');
            $table->timestamp('last_login_at')->nullable()->after('auth_version');
            $table->unsignedBigInteger('created_by_id')->nullable()->after('last_login_at');
            $table->unsignedBigInteger('updated_by_id')->nullable()->after('created_by_id');
        });

        // Staff accounts may sign in with a username only.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        Schema::create('user_admin_changes', function (Blueprint $table) {
            $table->id();
            // Historical IDs survive any separately approved account deletion.
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 32);
            $table->json('changed_fields');
            $table->json('before');
            $table->json('after');
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_admin_changes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'is_active', 'must_change_password', 'auth_version', 'last_login_at', 'created_by_id', 'updated_by_id']);
        });
    }
};
