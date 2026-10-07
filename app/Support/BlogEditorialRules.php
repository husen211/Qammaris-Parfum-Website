<?php

namespace App\Support;

use App\Models\BlogCategory;
use Illuminate\Validation\Rule;

final class BlogEditorialRules
{
    public static function fields(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:500000'],
            'category' => ['nullable', 'string', Rule::exists(BlogCategory::class, 'name')],
            'author' => ['nullable', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array', 'max:30'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('blog_tags', 'id')],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
