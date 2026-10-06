<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('publication_status', 20)->default('published')->after('is_active')->index();
            $table->timestamp('published_at')->nullable()->after('publication_status');
            $table->timestamp('archived_at')->nullable()->after('published_at');
            $table->string('availability_status', 20)->default('unknown')->after('archived_at')->index();
            $table->unsignedInteger('stock_quantity')->nullable()->after('availability_status');
            $table->string('availability_source', 50)->nullable()->after('stock_quantity');
            $table->timestamp('availability_checked_at')->nullable()->after('availability_source');
        });

        DB::table('products')
            ->where('is_active', false)
            ->update(['publication_status' => 'archived']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['publication_status']);
            $table->dropIndex(['availability_status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'publication_status',
                'published_at',
                'archived_at',
                'availability_status',
                'stock_quantity',
                'availability_source',
                'availability_checked_at',
            ]);
        });
    }
};
