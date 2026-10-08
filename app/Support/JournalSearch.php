<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class JournalSearch
{
    public function __construct(private BlogHtmlSanitizer $sanitizer) {}

    public function apply(Builder $query, string $term): void
    {
        $candidates = (clone $query)->with(['tags' => fn ($tags) => $tags->where('is_active', true)])->get();
        $idsByPost = [];
        $htmlByPost = [];
        foreach ($candidates as $post) {
            $htmlByPost[$post->id] = $this->sanitizer->sanitize($post->content);
            preg_match_all('/<div data-qammaris-product="([1-9][0-9]*)"/', $htmlByPost[$post->id], $matches);
            $idsByPost[$post->id] = array_unique(array_merge($post->related_product_ids ?? [], $matches[1]));
        }
        $productIds = array_unique(array_merge([], ...array_values($idsByPost)));
        $products = Product::published()->with('brand:id,name')->whereIn('id', $productIds)->get(['id', 'name', 'brand_id'])->keyBy('id');
        $scores = [];
        $needle = SearchMatcher::normalize($term);
        foreach ($candidates as $post) {
            $productTexts = [];
            foreach ($idsByPost[$post->id] as $id) {
                if ($product = $products->get($id)) {
                    $productTexts[] = $product->name.' '.$product->brand?->name;
                }
            }
            $groups = [[$post->title], [$post->excerpt], $post->tags->pluck('name')->all(), $productTexts];
            $best = null;
            foreach ($groups as $priority => $texts) {
                $score = SearchMatcher::score($term, $texts);
                if ($score !== null) {
                    $best = min($best ?? PHP_INT_MAX, $priority * 1000 + $score);
                }
            }
            $combined = SearchMatcher::score($term, array_merge(...$groups));
            if ($combined !== null) {
                $best = min($best ?? PHP_INT_MAX, 4000 + $combined);
            }
            // Body matching is a literal normalized phrase, never typo/substring matching.
            $text = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $htmlByPost[$post->id]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(Str::ascii($text))));
            if ($needle !== '' && preg_match('/(?:^| )'.preg_quote($needle, '/').'(?: |$)/', $body)) {
                $best ??= 10000;
            }
            if ($best !== null) {
                $scores[$post->id] = $best;
            }
        }
        $query->whereIn('blog_posts.id', array_keys($scores));
        if ($scores !== []) {
            $bindings = [];
            foreach ($scores as $id => $score) {
                array_push($bindings, $id, $score);
            }
            $query->orderByRaw('CASE blog_posts.id '.implode(' ', array_fill(0, count($scores), 'WHEN ? THEN ?')).' ELSE 999999 END', $bindings);
        }
    }
}
