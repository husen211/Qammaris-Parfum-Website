<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->string('image_acquisition_status', 30)->nullable()->after('applied_at');
            $table->foreignId('image_acquisition_requested_by')->nullable()->after('image_acquisition_status')->constrained('users')->nullOnDelete();
            $table->timestamp('image_acquisition_requested_at')->nullable()->after('image_acquisition_requested_by');
            $table->timestamp('image_acquisition_completed_at')->nullable()->after('image_acquisition_requested_at');
            $table->json('image_acquisition_outcomes')->nullable()->after('image_acquisition_completed_at');

            $table->index(['batch_id', 'image_acquisition_status'], 'import_rows_image_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_import_rows', function (Blueprint $table) {
            $table->dropIndex('import_rows_image_status_index');
            $table->dropConstrainedForeignId('image_acquisition_requested_by');
            $table->dropColumn([
                'image_acquisition_status',
                'image_acquisition_requested_at',
                'image_acquisition_completed_at',
                'image_acquisition_outcomes',
            ]);
        });
    }
};
