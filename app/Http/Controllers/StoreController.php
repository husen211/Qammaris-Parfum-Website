<?php

namespace App\Http\Controllers;

use App\Models\StoreInfo;

class StoreController extends Controller
{
    public function location()
    {
        $storeInfo = cache()->remember('store_info', 3600, function () {
            return StoreInfo::first() ?? new StoreInfo();
        });
        $fallbackAddress = "Qammaris Perfumes\nJl Sis Aljufri, Palu Barat\nKota Palu, Sulawesi Tengah";
        $addressTextRaw = trim((string) $storeInfo->address);
        $addressText = ($addressTextRaw === '' || str_contains(strtolower($addressTextRaw), 'contoh'))
            ? $fallbackAddress
            : $addressTextRaw;
        $defaultEmbedSrc = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.3280930730352!2d119.85686147529862!3d-0.8981817990931196!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2d8bedf9428ef169%3A0xcc6edf75dd6bb931!2sQammaris%20Perfumes!5e0!3m2!1sid!2sid!4v1768991591896!5m2!1sid!2sid';
        $mapsEmbedRaw = $storeInfo->google_maps_embed ?: $defaultEmbedSrc;
        $mapsLink = $this->resolveMapsLink($addressText, $mapsEmbedRaw);
        $whatsappNumber = '085144924931';
        $whatsappLink = $this->resolveWhatsappLink($whatsappNumber);
        $phoneDisplay = $whatsappNumber;
        $instagramUrl = $storeInfo->instagram_url ?: 'https://www.instagram.com/qammaris?igsh=bTUyeHFzeXdtc3E4';
        $shopeeUrl = $storeInfo->shopee_url ?: 'https://shopee.co.id/qammarisperfumes';
        $facebookUrl = $storeInfo->facebook_url ?: 'https://www.facebook.com/share/17taPWJ4Rg/?mibextid=wwXIfr';
        $tokopediaUrl = $storeInfo->tokopedia_url ?? '';
        $tiktokUrl = $storeInfo->tiktok_url ?? 'https://www.tiktok.com/@qammaris.parfum';
        $tiktokShopUrl = $storeInfo->tiktokshop_url ?? 'https://vt.tiktok.com/ZSajpqEPN/?page=Mall';
        $mapsEmbedSrc = $this->resolveMapsEmbedSrc($mapsEmbedRaw, $addressText, $defaultEmbedSrc);
        $formattedAddress = trim((string) $storeInfo->formatted_address);
        if ($formattedAddress === '' || str_contains(strtolower($formattedAddress), 'contoh')) {
            $displayAddress = nl2br(e($fallbackAddress));
        } else {
            $displayAddress = nl2br(e($formattedAddress));
        }

        return view('store.location', compact(
            'storeInfo',
            'mapsLink',
            'whatsappLink',
            'phoneDisplay',
            'instagramUrl',
            'tokopediaUrl',
            'shopeeUrl',
            'facebookUrl',
            'tiktokUrl',
            'tiktokShopUrl',
            'mapsEmbedSrc',
            'displayAddress'
        ));
    }
    
    public function about()
    {
        $storeInfo = cache()->remember('store_info', 3600, function () {
            return StoreInfo::first() ?? new StoreInfo();
        });

        return view('store.about', compact('storeInfo'));
    }

    private function resolveMapsLink(?string $address, ?string $explicitEmbed = null): string
    {
        $explicitEmbed = trim((string) $explicitEmbed);
        if ($explicitEmbed !== '' && str_contains($explicitEmbed, 'pb=')) {
            // Derive a view link from the embed if possible
            return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address ?: 'Qammaris Perfumes Palu');
        }

        $address = trim((string) $address);

        if ($address === '') {
            return 'https://maps.app.goo.gl/npKqotHHTo2AAyag9';
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
    }

    private function resolveWhatsappLink(?string $number): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62' . substr($normalized, 1);
        }

        return 'https://wa.me/' . $normalized;
    }

    private function resolveWhatsappNumber(?string $number): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);

        if ($normalized === '' || $normalized === '6282271636339' || $normalized === '082271636339') {
            return '085144924931';
        }

        return $number;
    }

    private function resolveMapsEmbed(?string $embed, ?string $address): string
    {
        $embed = trim((string) $embed);
        if ($embed !== '') {
            if (str_contains($embed, '<iframe')) {
                return $embed;
            }
            return $embed;
        }

        $query = trim((string) $address);
        if ($query === '') {
            $query = 'Qammaris Perfumes Palu';
        }

        return 'https://www.google.com/maps?q=' . urlencode($query) . '&t=&z=16&ie=UTF8&iwloc=&output=embed';
    }

    private function resolveMapsEmbedSrc(?string $embed, ?string $address, ?string $fallbackSrc = null): string
    {
        $embed = trim((string) $embed);

        if ($embed !== '') {
            $candidate = $embed;
            if (preg_match('/src=["\']([^"\']+)/i', $embed, $matches)) {
                $candidate = $matches[1];
            }

            if (str_contains($candidate, 'embed?') || str_contains($candidate, 'output=embed')) {
                return $candidate;
            }

            if ($fallbackSrc) {
                return $fallbackSrc;
            }

            return $candidate;
        }

        $query = trim((string) $address);
        if ($query === '') {
            $query = 'Qammaris Perfumes Palu';
        }

        if ($fallbackSrc) {
            return $fallbackSrc;
        }

        return 'https://www.google.com/maps?q=' . urlencode($query) . '&t=&z=16&ie=UTF8&iwloc=&output=embed';
    }
}
