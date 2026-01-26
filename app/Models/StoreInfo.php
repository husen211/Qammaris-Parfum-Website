<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreInfo extends Model
{
    use HasFactory;

    protected $table = 'store_info';

    protected $fillable = [
        'store_name',
        'tagline',
        'whatsapp_number',
        'phone_number',
        'email',
        'address',
        'google_maps_embed',
        'instagram_url',
        'facebook_url',
        'tokopedia_url',
        'shopee_url',
        'tiktokshop_url',
        'about_description',
        'hero_title',
        'hero_subtitle',
    ];

    /**
     * Get the single store info instance (Singleton pattern)
     */
    public static function getSetting()
    {
        return self::first() ?? self::create([
            'store_name' => 'Qammaris Perfumes',
            'whatsapp_number' => '6282271636339',
            'hero_title' => 'Explore Premium Middle Eastern Fragrances',
            'hero_subtitle' => 'Koleksi parfum otentik dari brand ternama Timur Tengah',
        ]);
    }

    /**
     * Accessor: WhatsApp link
     */
    public function getWhatsappLinkAttribute(): string
    {
        return 'https://wa.me/' . $this->whatsapp_number;
    }

    /**
     * Accessor: Formatted address (HTML)
     */
    public function getFormattedAddressAttribute(): string
    {
        return nl2br(e($this->address));
    }
}