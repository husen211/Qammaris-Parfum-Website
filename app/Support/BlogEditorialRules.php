<?php

namespace App\Support;

use App\Models\BlogCategory;
use Illuminate\Validation\Rule;

final class BlogEditorialRules
{
    public static function fields(): array
    {
        return BlogComponentRules::fields() + [
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:500000'],
            'category' => ['nullable', 'string', Rule::exists(BlogCategory::class, 'name')],
            'author' => ['nullable', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'canonical_url' => self::httpsUrl(),
            'seo_indexable' => ['sometimes', 'boolean'],
            'seo_followable' => ['sometimes', 'boolean'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'],
            'og_image_url' => self::httpsUrl(),
            'tag_ids' => ['sometimes', 'array', 'max:30'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('blog_tags', 'id')],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    private static function httpsUrl(): array
    {
        return ['bail', 'nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
            if (! JournalMetadata::validHttps($value)) {
                $fail('Gunakan URL HTTPS lengkap tanpa kredensial atau fragmen.');
            }
        }];
    }
}
