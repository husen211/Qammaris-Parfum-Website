<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Qammaris Perfumes public website PWA resources (PWA-01). Served without sessions or cookies and free of
 * customer data. Separate identity from the Admin PWA (AdminPwaController): own id, scope, name and icons.
 */
class PublicPwaController extends Controller
{
    public const SCOPE = '/';

    public const NAME = 'Qammaris Perfumes';

    public function manifest(): JsonResponse
    {
        abort_unless(config('store.pwa_enabled'), 404);

        return response()->json([
            'id' => self::SCOPE,
            'name' => self::NAME,
            'short_name' => 'Qammaris',
            'description' => 'Katalog parfum original Timur Tengah dari Qammaris Perfumes, Palu.',
            'lang' => 'id',
            'dir' => 'ltr',
            'start_url' => self::SCOPE,
            'scope' => self::SCOPE,
            'display' => 'standalone',
            'background_color' => '#F9F9F7',
            'theme_color' => '#FFFFFF',
            'categories' => ['shopping', 'lifestyle'],
            'prefer_related_applications' => false,
            'icons' => [
                ['src' => '/images/pwa/public-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/public-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/public-icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Katalog', 'url' => '/products'],
                ['name' => 'Keranjang', 'url' => '/cart'],
                ['name' => 'Pesanan Saya', 'url' => '/pesanan-saya'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function serviceWorker(): Response
    {
        $config = [
            'version' => $this->version(),
            'enabled' => (bool) config('store.pwa_enabled'),
            'offlineUrl' => route('pwa.offline', [], false),
        ];
        $source = file_get_contents(resource_path('js/public-sw.js'));

        return response('const PUBLIC_SW = '.json_encode($config, JSON_UNESCAPED_SLASHES).";\n".$source, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => self::SCOPE,
            // Revalidated on every check so a deploy or the kill switch reaches installed copies.
            'Cache-Control' => 'no-cache, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function offline(): Response
    {
        return response()->view('pwa.offline', [], 200, [
            'Cache-Control' => 'no-cache, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** Changes with every frontend build or worker change, so old public caches are replaced. */
    private function version(): string
    {
        $build = public_path('build/manifest.json');

        return substr(sha1(implode('|', [
            is_file($build) ? md5_file($build) : 'no-build',
            md5_file(resource_path('js/public-sw.js')),
            md5_file(resource_path('views/pwa/offline.blade.php')),
        ])), 0, 12);
    }
}
