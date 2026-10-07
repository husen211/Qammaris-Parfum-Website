<?php

namespace App\Support;

use App\Models\Product;
use DOMDocument;
use DOMXPath;

final class RenderBlogContent
{
    public function __construct(private BlogHtmlSanitizer $sanitizer) {}

    public function handle(?string $content): string
    {
        return $this->document($content)['html'];
    }

    public function document(?string $content): array
    {
        $html = $this->sanitizer->sanitize($content);
        preg_match_all('/<div data-qammaris-product="([1-9][0-9]*)"><\/div>/', $html, $matches);
        $products = $matches[1] === [] ? collect() : Product::published()->with('brand:id,name')
            ->whereIn('id', array_unique($matches[1]))->get(['id', 'name', 'slug', 'brand_id'])->keyBy('id');

        $html = preg_replace_callback('/<div data-qammaris-product="([1-9][0-9]*)"><\/div>/', function ($match) use ($products) {
            $product = $products->get((int) $match[1]);

            return $product ? '<p><a href="'.e(route('products.show', $product->slug)).'">Lihat '.e($product->name).'</a></p>' : '';
        }, $html);

        if ($html === '') {
            return ['html' => '', 'toc' => [], 'linkedProducts' => $products];
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body><div id="journal-root">'.$html.'</div></body></html>', LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementById('journal-root');
            $xpath = new DOMXPath($document);
            $toc = [];
            $h2Count = 0;
            foreach ($xpath->query('.//h2|.//h3', $root) as $heading) {
                $text = trim($heading->textContent);
                if ($text === '') {
                    continue;
                }
                $anchor = 'journal-section-'.(count($toc) + 1);
                $heading->setAttribute('id', $anchor);
                $h2Count += $heading->tagName === 'h2' ? 1 : 0;
                $toc[] = ['id' => $anchor, 'text' => $text, 'level' => $heading->tagName];
            }
            foreach (iterator_to_array($xpath->query('.//table', $root)) as $table) {
                $wrapper = $document->createElement('div');
                $wrapper->setAttribute('class', 'journal-table');
                $wrapper->setAttribute('role', 'region');
                $wrapper->setAttribute('aria-label', 'Tabel artikel');
                $wrapper->setAttribute('tabindex', '0');
                $table->parentNode->insertBefore($wrapper, $table);
                $wrapper->appendChild($table);
            }
            $rendered = '';
            foreach ($root->childNodes as $child) {
                $rendered .= $document->saveHTML($child);
            }

            return ['html' => $rendered, 'toc' => $h2Count >= 3 ? $toc : [], 'linkedProducts' => $products];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
