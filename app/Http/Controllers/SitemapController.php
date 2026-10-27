<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use App\Support\CatalogCollection;
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
            $products = Product::published()->with('brand:id,slug')->get(['slug', 'updated_at', 'gender', 'brand_id']);
            $posts = BlogPost::published()->where('seo_indexable', true)
                ->get(['slug', 'canonical_url', 'content_updated_at', 'published_at']);
            // SEO-01: lastmod only where a real change date exists; the noindex quiz stays out of the sitemap.
            $catalogUpdated = $products->max('updated_at')?->toAtomString();
            $urls = [
                ['loc' => url('/'), 'lastmod' => $catalogUpdated],
                ['loc' => route('products.index'), 'lastmod' => $catalogUpdated],
                ['loc' => route('blog.index'), 'lastmod' => $posts->map(fn ($post) => $post->content_updated_at ?? $post->published_at)->max()?->toAtomString()],
                ['loc' => route('store.about')],
                ['loc' => route('store.location')],
                ['loc' => route('store.virtual-tour')],
            ];

            // SEO-03: permanent collection and brand pages, dated by their newest product change.
            foreach (CatalogCollection::links() as $link) {
                $members = $link['group'] === 'gender'
                    ? $products->where('gender', config("catalog_collections.genders.{$link['slug']}.gender"))
                    : $products->filter(fn (Product $product) => $product->brand?->slug === $link['slug']);
                $urls[] = ['loc' => $link['url'], 'lastmod' => $members->max('updated_at')?->toAtomString()];
            }

            foreach ($products as $product) {
                $urls[] = [
                    'loc' => route('products.show', $product->slug),
                    'lastmod' => $product->updated_at?->toAtomString(),
                ];
            }

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
