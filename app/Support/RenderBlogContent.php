<?php

namespace App\Support;

use App\Models\BlogPost;
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

    public function document(?string $content, ?BlogPost $post = null): array
    {
        $html = $this->sanitizer->sanitize($content);
        preg_match_all('/<div data-qammaris-product="([1-9][0-9]*)"><\/div>/', $html, $matches);
        $ids = array_values(array_unique(array_merge($post?->related_product_ids ?? [], $matches[1])));
        $productsById = $ids === [] ? collect() : Product::published()->with(['brand', 'activeOffer', 'primaryImage'])
            ->whereIn('id', $ids)->get()->keyBy('id');
        $products = collect($ids)->map(fn ($id) => $productsById->get((int) $id))->filter()->values();

        $html = preg_replace_callback('/<div data-qammaris-product="([1-9][0-9]*)"><\/div>/', function ($match) use ($productsById) {
            $product = $productsById->get((int) $match[1]);

            return $product ? view('blog._product', compact('product'))->render() : '';
        }, $html);
        $media = $post?->exists ? $post->media()->whereNull('archived_at')->get()->keyBy('id') : collect();
        preg_match_all('/data-qammaris-article="([1-9][0-9]*)"/', $html, $articles);
        $articlesById = BlogPost::published()->whereIn('id', $articles[1])->where('id', '!=', $post?->id ?? 0)->get()->keyBy('id');
        $html = preg_replace_callback('/<div data-qammaris-(media|gallery|article|youtube|callout|cta)="[^"]*"[^>]*><\/div>/', function ($match) use ($media, $articlesById) {
            $node = new DOMDocument;
            $node->loadHTML('<?xml encoding="UTF-8">'.$match[0], LIBXML_NOERROR | LIBXML_NOWARNING);
            $element = $node->getElementsByTagName('div')->item(0);
            $kind = $match[1];
            $value = $element->getAttribute('data-qammaris-'.$kind);
            if ($kind === 'media' || $kind === 'gallery') {
                $items = collect(explode(',', $value))->map(fn ($id) => $media->get((int) $id))->filter()->values();

                return $items->isEmpty() ? '' : view('blog._gallery', ['items' => $items, 'gallery' => $kind === 'gallery'])->render();
            }
            if ($kind === 'article') {
                $article = $articlesById->get((int) $value);

                return $article ? '<div data-journal-component class="journal-inline-article"><span>Baca juga</span><a href="'.e(route('blog.show', $article->slug)).'">'.e($article->title).' →</a></div>' : '';
            }
            if ($kind === 'youtube') {
                return '<div data-journal-component class="journal-video"><iframe src="https://www.youtube-nocookie.com/embed/'.e($value).'" title="Video artikel YouTube" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen></iframe><a href="https://www.youtube.com/watch?v='.e($value).'" target="_blank" rel="noopener noreferrer">Buka video di YouTube ↗</a></div>';
            }
            if ($kind === 'cta') {
                return '<div data-journal-component class="journal-inline-cta"><a class="journal-button" href="'.e($value).'">'.e($element->getAttribute('data-label')).'</a></div>';
            }

            return '<aside data-journal-component class="journal-callout journal-callout-'.e($value).'"><strong>'.e($element->getAttribute('data-title')).'</strong><p>'.e($element->getAttribute('data-text')).'</p></aside>';
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
            foreach ($xpath->query('.//h2[not(ancestor::*[@data-journal-component])]|.//h3[not(ancestor::*[@data-journal-component])]', $root) as $heading) {
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
