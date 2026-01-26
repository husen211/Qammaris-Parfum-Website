<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FragranceQuizService
{
    public function questions(): array
    {
        return config('fragrance_quiz.questions', []);
    }

    public function recommend(array $answers): array
    {
        $tagScores = $this->scoreTags($answers);
        arsort($tagScores);

        $topTags = array_keys($tagScores);
        $maxTags = (int) config('fragrance_quiz.max_tags', 3);
        $topTags = array_slice($topTags, 0, $maxTags);

        $products = $this->rankProducts($topTags, $tagScores, $answers);

        return [
            'tags' => $topTags,
            'tag_labels' => $this->labelsForTags($topTags),
            'summary' => $this->summaryForTags($topTags),
            'products' => $products,
            'scores' => $tagScores,
        ];
    }

    private function scoreTags(array $answers): array
    {
        $optionTags = config('fragrance_quiz.option_tags', []);
        $scores = [];

        foreach ($answers as $question => $value) {
            $tags = $optionTags[$question][$value] ?? [];
            foreach ($tags as $tag => $weight) {
                $scores[$tag] = ($scores[$tag] ?? 0) + $weight;
            }
        }

        return $scores;
    }

    private function rankProducts(array $topTags, array $tagScores, array $answers)
    {
        $query = Product::with(['brand', 'primaryImage'])->active();

        $genderMap = config('fragrance_quiz.gender_map', []);
        $genderKey = $answers['gender'] ?? 'all';
        $gender = $genderMap[$genderKey] ?? null;
        if ($gender) {
            $query->where('gender', $gender);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $products = Product::with(['brand', 'primaryImage'])->active()->get();
        }

        $keywords = config('fragrance_quiz.tag_keywords', []);
        $maxRecommendations = (int) config('fragrance_quiz.max_recommendations', 4);

        $scored = $products->map(function (Product $product) use ($topTags, $tagScores, $keywords) {
            $text = $this->productText($product);
            $score = 0;

            foreach ($topTags as $tag) {
                $tagWeight = $tagScores[$tag] ?? 0;
                foreach ($keywords[$tag] ?? [] as $keyword) {
                    if ($this->matchesKeyword($text, $keyword)) {
                        $score += $tagWeight;
                    }
                }
            }

            return [
                'product' => $product,
                'score' => $score,
            ];
        });

        $hasMatches = $scored->max('score') > 0;

        if (! $hasMatches) {
            return Product::with(['brand', 'primaryImage'])
                ->active()
                ->bestSellers()
                ->take($maxRecommendations)
                ->get();
        }

        return $scored->sortByDesc('score')
            ->pluck('product')
            ->take($maxRecommendations)
            ->values();
    }

    private function productText(Product $product): string
    {
        $notes = '';
        if (is_array($product->fragrance_notes)) {
            $notes = implode(' ', Arr::flatten($product->fragrance_notes));
        }

        $text = implode(' ', [
            $product->name,
            $product->description,
            $notes,
        ]);

        return Str::lower($text);
    }

    private function matchesKeyword(string $text, string $keyword): bool
    {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/';

        return preg_match($pattern, $text) === 1;
    }

    private function labelsForTags(array $tags): array
    {
        $labels = config('fragrance_quiz.tag_labels', []);

        return array_map(function ($tag) use ($labels) {
            return $labels[$tag] ?? Str::title($tag);
        }, $tags);
    }

    private function summaryForTags(array $tags): string
    {
        if (empty($tags)) {
            return 'Kami pilihkan parfum yang aman dipakai untuk berbagai aktivitas.';
        }

        $descriptions = config('fragrance_quiz.tag_descriptions', []);
        $main = $tags[0];

        return $descriptions[$main] ?? 'Profil aroma ini cocok untuk keseharian dan berbagai suasana.';
    }
}
