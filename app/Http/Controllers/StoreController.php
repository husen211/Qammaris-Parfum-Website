<?php

namespace App\Http\Controllers;

use App\Models\StoreInfo;
use App\Support\StoreProfile;

class StoreController extends Controller
{
    public function location()
    {
        $storeInfo = cache()->remember('store_info', 3600, function () {
            return StoreInfo::first() ?? new StoreInfo;
        });
        $profile = config('store.profile');
        $fallbackAddress = $profile['name']."\n".$profile['street']."\n".$profile['locality'].', '.$profile['region'].' '.$profile['postal_code'];
        $storeSchema = ['@context' => 'https://schema.org', '@graph' => [StoreProfile::schema(), [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Lokasi Toko', 'item' => route('store.location')],
            ],
        ]]];
        $addressTextRaw = trim((string) $storeInfo->address);
        $addressText = ($addressTextRaw === '' || str_contains(strtolower($addressTextRaw), 'contoh'))
            ? $fallbackAddress
            : $addressTextRaw;
        $defaultEmbedSrc = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.3280930730352!2d119.85686147529862!3d-0.8981817990931196!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2d8bedf9428ef169%3A0xcc6edf75dd6bb931!2sQammaris%20Perfumes!5e0!3m2!1sid!2sid!4v1768991591896!5m2!1sid!2sid';
        $mapsEmbedRaw = $storeInfo->google_maps_embed ?: $defaultEmbedSrc;
        $mapsLink = $this->resolveMapsLink($addressText, $mapsEmbedRaw);
        // Production always has a number; test environments may have none (ORD-07), then the link stays disabled.
        $whatsappNumber = StoreInfo::usesTestWhatsapp() ? $storeInfo->whatsapp_number : ($storeInfo->whatsapp_number ?: '6285144924931');
        $whatsappLink = $this->resolveWhatsappLink($whatsappNumber);
        $phoneDisplay = $this->formatPhoneDisplay($whatsappNumber);
        $instagramUrl = $storeInfo->instagram_url ?: 'https://www.instagram.com/qammaris';
        $shopeeUrl = $storeInfo->shopee_url ?: 'https://shopee.co.id/qammarisperfumes';
        $facebookUrl = $storeInfo->facebook_url ?: 'https://www.facebook.com/share/17taPWJ4Rg/?mibextid=wwXIfr';
        $tokopediaUrl = $storeInfo->tokopedia_url ?? '';
        $tiktokUrl = $storeInfo->tiktok_url ?? 'https://www.tiktok.com/@qammaris.parfum?_r=1&_t=ZS-93QXMasV6w5';
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
            'displayAddress',
            'storeSchema'
        ));
    }

    public function virtualTour()
    {
        $config = config('store_virtual_tour');
        $spots = collect($config['spots'])->map(fn (array $spot, string $id) => [
            'id' => $id,
            'name' => $spot['name'],
            'yaw' => $spot['yaw'],
            'theta' => $spot['theta'],
            'plan' => $spot['plan'],
            'images' => [
                'small' => asset("images/virtual-tour/{$id}-4096.jpg"),
                'large' => asset("images/virtual-tour/{$id}-6144.jpg"),
            ],
            'preview' => asset("images/virtual-tour/{$id}-preview.webp"),
            'links' => $spot['links'],
        ])->values();
        $tour = ['start' => $config['start'], 'spots' => $spots];
        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Lokasi Toko', 'item' => route('store.location')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Virtual Tour', 'item' => route('store.virtual-tour')],
            ],
        ];

        return view('store.virtual-tour', compact('tour', 'spots', 'breadcrumbSchema'));
    }

    public function about()
    {
        $storeInfo = cache()->remember('store_info', 3600, function () {
            return StoreInfo::first() ?? new StoreInfo;
        });

        $aboutContent = config('store_about');
        $aboutMedia = config('store_about_media');
        $aboutAddress = trim((string) $storeInfo->address);
        if ($aboutAddress === '' || str_contains(strtolower($aboutAddress), 'contoh')) {
            $aboutAddress = StoreProfile::fullAddress();
        }
        $storeId = StoreProfile::id();
        $aboutSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'AboutPage',
                    '@id' => route('store.about').'#webpage',
                    'url' => route('store.about'),
                    'name' => 'Tentang Qammaris — Experience Store Parfum di Palu',
                    'inLanguage' => 'id-ID',
                    'mainEntity' => ['@id' => $storeId],
                    'breadcrumb' => ['@id' => route('store.about').'#breadcrumb'],
                ],
                StoreProfile::schema(),
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => route('store.about').'#breadcrumb',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tentang Qammaris', 'item' => route('store.about')],
                    ],
                ],
            ],
        ];

        return view('store.about', compact('storeInfo', 'aboutContent', 'aboutMedia', 'aboutAddress', 'aboutSchema'));
    }

    private function resolveMapsLink(?string $address, ?string $explicitEmbed = null): string
    {
        $explicitEmbed = trim((string) $explicitEmbed);
        if ($explicitEmbed !== '' && str_contains($explicitEmbed, 'pb=')) {
            // Derive a view link from the embed if possible
            return 'https://www.google.com/maps/search/?api=1&query='.urlencode($address ?: 'Qammaris Perfumes Palu');
        }

        $address = trim((string) $address);

        if ($address === '') {
            return 'https://maps.app.goo.gl/npKqotHHTo2AAyag9';
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($address);
    }

    private function resolveWhatsappLink(?string $number): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return 'https://wa.me/'.$normalized;
    }

    private function resolveWhatsappNumber(?string $number): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);

        if ($normalized === '') {
            return '6285144924931';
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        return $normalized === '6285144924931' ? $normalized : '6285144924931';
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

        return 'https://www.google.com/maps?q='.urlencode($query).'&t=&z=16&ie=UTF8&iwloc=&output=embed';
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

        return 'https://www.google.com/maps?q='.urlencode($query).'&t=&z=16&ie=UTF8&iwloc=&output=embed';
    }

    private function formatPhoneDisplay(?string $number): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $number);

        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        if (preg_match('/^(\d{2})(\d{3})(\d{4})(\d{4})$/', $normalized, $matches)) {
            return "+{$matches[1]} {$matches[2]}-{$matches[3]}-{$matches[4]}";
        }

        return $normalized;
    }
}
