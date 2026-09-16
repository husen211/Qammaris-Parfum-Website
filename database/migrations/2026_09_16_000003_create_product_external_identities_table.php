<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('external_product_id', 191);
            $table->timestamps();

            $table->unique(
                ['provider', 'external_product_id'],
                'product_external_identities_provider_code_unique'
            );
            $table->unique(
                ['product_id', 'provider'],
                'product_external_identities_product_provider_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_external_identities');
    }
};
