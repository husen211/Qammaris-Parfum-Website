<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->decimal('base_price', 10, 2)->nullable()->comment('Mirror harga offer aktif')->change();
            $table->enum('gender', ['Unisex', 'Pria', 'Wanita'])->nullable()->default(null)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('sku')->nullable()->comment('Kode katalog opsional')->change();
            $table->unique('product_id', 'product_variants_one_offer_per_product');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variants_one_offer_per_product');
            $table->string('sku')->nullable(false)->comment('Stock Keeping Unit')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable(false)->change();
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->decimal('base_price', 10, 2)->nullable(false)->comment('Harga referensi terendah')->change();
            $table->enum('gender', ['Unisex', 'Pria', 'Wanita'])->nullable(false)->default('Unisex')->change();
        });
    }
};
