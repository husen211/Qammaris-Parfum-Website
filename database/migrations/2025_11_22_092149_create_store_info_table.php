<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_info', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->default('Qammaris Perfumes');
            $table->string('tagline')->nullable();
            $table->string('whatsapp_number');
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('google_maps_embed')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('tokopedia_url')->nullable();
            $table->string('shopee_url')->nullable();
            $table->string('tiktokshop_url')->nullable();
            $table->longText('about_description')->nullable();
            $table->string('hero_title')->default('Explore Premium Middle Eastern Fragrances');
            $table->string('hero_subtitle')->default('Koleksi parfum otentik dari brand ternama Timur Tengah');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_info');
    }
};