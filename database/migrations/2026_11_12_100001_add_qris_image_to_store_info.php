<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ORD-13: the store's static QRIS image (path on the product media disk), shown on the customer payment page. */
    public function up(): void
    {
        Schema::table('store_info', function (Blueprint $table) {
            $table->string('qris_image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_info', function (Blueprint $table) {
            $table->dropColumn('qris_image_path');
        });
    }
};
