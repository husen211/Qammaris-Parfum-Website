<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = cache()->remember('sitemap.xml', 3600, function () {
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

            $products = Product::active()->get(['slug', 'updated_at']);
            foreach ($products as $product) {
                $urls[] = [
                    'loc' => route('products.show', $product->slug),
                    'lastmod' => $product->updated_at?->toAtomString(),
                ];
            }

            $posts = BlogPost::published()->get(['slug', 'updated_at', 'published_at']);
            foreach ($posts as $post) {
                $urls[] = [
                    'loc' => route('blog.show', $post->slug),
                    'lastmod' => ($post->published_at ?? $post->updated_at)?->toAtomString(),
                ];
            }

            return $this->renderXml($urls);
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function renderXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $loc = htmlspecialchars($url['loc'], ENT_XML1);
            $xml .= '<url><loc>' . $loc . '</loc>';

            if (!empty($url['lastmod'])) {
                $xml .= '<lastmod>' . $url['lastmod'] . '</lastmod>';
            }

            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
