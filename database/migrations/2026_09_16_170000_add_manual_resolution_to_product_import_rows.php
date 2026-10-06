<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->string('resolution_status', 30)->nullable()->after('applied_at');
            $table->json('resolution_fields')->nullable()->after('resolution_status');
            $table->foreignId('resolved_by')->nullable()->after('resolution_fields')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            $table->string('resolution_message', 500)->nullable()->after('resolved_at');
            $table->json('resolution_before_snapshot')->nullable()->after('resolution_message');
            $table->json('resolution_after_snapshot')->nullable()->after('resolution_before_snapshot');

            $table->index(['batch_id', 'resolution_status']);
        });
    }

    public function down(): void
    {
        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'resolution_status']);
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn([
                'resolution_status',
                'resolution_fields',
                'resolved_at',
                'resolution_message',
                'resolution_before_snapshot',
                'resolution_after_snapshot',
            ]);
        });
    }
};
