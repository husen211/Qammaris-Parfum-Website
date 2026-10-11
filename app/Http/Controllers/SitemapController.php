<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Support\JournalMetadata;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $cached = cache()->get('sitemap.xml');
        if (! is_array($cached) || ($cached['expires_at'] ?? 0) <= now()->timestamp) {
            $next = BlogPost::whereNull('archived_at')->where('is_published', true)->where('published_at', '>', now())->min('published_at');
            $expiresAt = min(now()->timestamp + 3600, $next ? Carbon::parse($next)->timestamp : PHP_INT_MAX);
            $urls = [
                [
                    'loc' => url('/'),
                    'lastmod' => now()->toAtomString(),
                ],
                [
                    'loc' => route('products.index'),
                    'lastmod' => now()->toAtomString(),
                ],
                [
                    'loc' => route('blog.index'),
                    'lastmod' => now()->toAtomString(),
                ],
                [
                    'loc' => route('store.about'),
                    'lastmod' => now()->toAtomString(),
                ],
                [
                    'loc' => route('store.location'),
                    'lastmod' => now()->toAtomString(),
                ],
                [
                    'loc' => route('quiz.index'),
                    'lastmod' => now()->toAtomString(),
                ],
            ];

            $products = Product::published()->get(['slug', 'updated_at']);
            foreach ($products as $product) {
                $urls[] = [
                    'loc' => route('products.show', $product->slug),
                    'lastmod' => $product->updated_at?->toAtomString(),
                ];
            }

            $posts = BlogPost::published()->where('seo_indexable', true)
                ->get(['slug', 'canonical_url', 'content_updated_at', 'published_at']);
            foreach ($posts as $post) {
                if (! JournalMetadata::selfCanonical($post)) {
                    continue;
                }
                $urls[] = [
                    'loc' => route('blog.show', $post->slug),
                    'lastmod' => ($post->content_updated_at ?? $post->published_at)?->toAtomString(),
                ];
            }

            $cached = ['xml' => $this->renderXml($urls), 'expires_at' => $expiresAt];
            cache()->put('sitemap.xml', $cached, max(1, $expiresAt - now()->timestamp));
        }

        return response($cached['xml'], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age='.max(0, $cached['expires_at'] - now()->timestamp));
    }

    private function renderXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $loc = htmlspecialchars($url['loc'], ENT_XML1);
            $xml .= '<url><loc>'.$loc.'</loc>';

            if (! empty($url['lastmod'])) {
                $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }

            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
