<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Qammaris Admin PWA resources (ORD-02b). Served without sessions or cookies; none of them contain
 * account or customer data, so they are safe for the service worker to cache.
 */
class AdminPwaController extends Controller
{
    // Without a trailing slash so the dashboard at /admin stays inside the installed app.
    public const SCOPE = '/admin';

    public const START_URL = '/admin/orders?source=pwa';

    public function manifest(): JsonResponse
    {
        abort_unless(config('admin.pwa_enabled'), 404);

        return response()->json([
            'id' => self::SCOPE,
            'name' => 'Qammaris Admin',
            'short_name' => 'QAM Admin',
            'description' => 'Pesanan online dan admin Qammaris untuk Owner dan staf.',
            'lang' => 'id',
            'dir' => 'ltr',
            'start_url' => self::START_URL,
            'scope' => self::SCOPE,
            'display' => 'standalone',
            'background_color' => '#F9F9F7',
            'theme_color' => '#1A1A1A',
            'prefer_related_applications' => false,
            'icons' => [
                ['src' => '/images/pwa/admin-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/admin-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/admin-icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Buat pesanan', 'url' => '/admin/orders/create'],
                ['name' => 'Pesanan online', 'url' => '/admin/orders'],
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
            'enabled' => (bool) config('admin.pwa_enabled'),
            'offlineUrl' => route('admin.pwa.offline', [], false),
        ];
        $source = file_get_contents(resource_path('js/admin-sw.js'));

        return response('const ADMIN_SW = '.json_encode($config, JSON_UNESCAPED_SLASHES).";\n".$source, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => self::SCOPE,
            // Browsers must revalidate the worker so a deploy or the kill switch reaches installed phones.
            'Cache-Control' => 'no-cache, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function offline(): Response
    {
        return response()->view('admin.pwa.offline', [], 200, ['Cache-Control' => 'no-cache, max-age=0']);
    }

    /** Changes with every frontend build or worker change, so old caches are replaced. */
    private function version(): string
    {
        $build = public_path('build/manifest.json');

        return substr(sha1(implode('|', [
            is_file($build) ? md5_file($build) : 'no-build',
            md5_file(resource_path('js/admin-sw.js')),
            md5_file(resource_path('views/admin/pwa/offline.blade.php')),
        ])), 0, 12);
    }
}
