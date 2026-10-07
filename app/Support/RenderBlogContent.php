<?php

namespace App\Support;

use App\Models\Product;

final class RenderBlogContent
{
    public function __construct(private BlogHtmlSanitizer $sanitizer) {}

    public function handle(?string $content): string
    {
        $html = $this->sanitizer->sanitize($content);
        preg_match_all('/<div data-qammaris-product="([1-9][0-9]*)"><\/div>/', $html, $matches);
        if ($matches[1] === []) {
            return $html;
        }
        $products = Product::published()->whereIn('id', array_unique($matches[1]))->get(['id', 'name', 'slug'])->keyBy('id');

        return preg_replace_callback('/<div data-qammaris-product="([1-9][0-9]*)"><\/div>/', function ($match) use ($products) {
            $product = $products->get((int) $match[1]);

            return $product ? '<p><a href="'.e(route('products.show', $product->slug)).'">Lihat '.e($product->name).'</a></p>' : '';
        }, $html);
    }
}
