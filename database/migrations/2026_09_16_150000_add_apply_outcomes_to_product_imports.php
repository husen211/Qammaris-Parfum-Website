<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_import_batches', function (Blueprint $table) {
            $table->foreignId('applied_by')->nullable()->after('actor_id')->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('status');
            $table->timestamp('failed_at')->nullable()->after('applied_at');
            $table->unsignedInteger('applied_rows')->default(0)->after('error_rows');
            $table->unsignedInteger('blocked_rows')->default(0)->after('applied_rows');
            $table->string('failure_message', 500)->nullable()->after('blocked_rows');
        });

        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->string('apply_status', 30)->default('pending')->after('payload_hash');
            $table->foreignId('applied_product_id')->nullable()->after('apply_status')->constrained('products')->nullOnDelete();
            $table->string('apply_message', 500)->nullable()->after('applied_product_id');
            $table->json('before_snapshot')->nullable()->after('apply_message');
            $table->json('after_snapshot')->nullable()->after('before_snapshot');
            $table->timestamp('applied_at')->nullable()->after('after_snapshot');

            $table->index(['batch_id', 'apply_status']);
        });
    }

    public function down(): void
    {
        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'apply_status']);
            $table->dropConstrainedForeignId('applied_product_id');
            $table->dropColumn([
                'apply_status',
                'apply_message',
                'before_snapshot',
                'after_snapshot',
                'applied_at',
            ]);
        });

        Schema::table('product_import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_by');
            $table->dropColumn([
                'applied_at',
                'failed_at',
                'applied_rows',
                'blocked_rows',
                'failure_message',
            ]);
        });
    }
};
