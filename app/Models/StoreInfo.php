<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreInfo extends Model
{
    use HasFactory;

    private const DEFAULT_WHATSAPP_NUMBER = '6285144924931';

    private const DEFAULT_INSTAGRAM_URL = 'https://www.instagram.com/qammaris';

    private const DEFAULT_TIKTOK_URL = 'https://www.tiktok.com/@qammaris.parfum?_r=1&_t=ZS-93QXMasV6w5';

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
            'whatsapp_number' => self::DEFAULT_WHATSAPP_NUMBER,
            'hero_title' => 'Explore Premium Middle Eastern Fragrances',
            'hero_subtitle' => 'Koleksi parfum otentik dari brand ternama Timur Tengah',
        ]);
    }

    /** ORD-07: UAT, staging, local and tests never contact the real store (see config/store.php). */
    public static function usesTestWhatsapp(): bool
    {
        return app()->environment(config('store.test_environments', []));
    }

    public function getWhatsappNumberAttribute($value): string
    {
        if (self::usesTestWhatsapp()) {
            $test = PhoneNumber::normalize(config('store.whatsapp_test_number'));

            // No test number (or the store number by mistake): no destination at all.
            return $test === null || $test === self::DEFAULT_WHATSAPP_NUMBER ? '' : $test;
        }

        $normalized = preg_replace('/\D+/', '', (string) $value);

        if ($normalized === '') {
            return self::DEFAULT_WHATSAPP_NUMBER;
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return $normalized === self::DEFAULT_WHATSAPP_NUMBER
            ? $normalized
            : self::DEFAULT_WHATSAPP_NUMBER;
    }

    public function getInstagramUrlAttribute($value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return self::DEFAULT_INSTAGRAM_URL;
        }

        if (str_contains($normalized, 'instagram.com/qammaris')) {
            return self::DEFAULT_INSTAGRAM_URL;
        }

        return $normalized;
    }

    public function getTiktokUrlAttribute($value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return self::DEFAULT_TIKTOK_URL;
        }

        if (str_contains($normalized, 'tiktok.com/@qammaris.parfum')) {
            return self::DEFAULT_TIKTOK_URL;
        }

        return $normalized;
    }

    /**
     * Accessor: WhatsApp link
     */
    public function getWhatsappLinkAttribute(): string
    {
        return $this->whatsapp_number === '' ? '' : 'https://wa.me/'.$this->whatsapp_number;
    }

    /**
     * Accessor: Formatted address (HTML)
     */
    public function getFormattedAddressAttribute(): string
    {
        return nl2br(e($this->address));
    }
}
