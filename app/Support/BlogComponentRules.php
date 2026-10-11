<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BlogComponentRules
{
    public static function fields(): array
    {
        return [
            'featured_media_id' => ['nullable', 'integer', 'min:1'],
            'related_product_ids' => ['sometimes', 'array', 'max:12'],
            'related_product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
            'related_article_ids' => ['sometimes', 'array', 'max:3'],
            'related_article_ids.*' => ['integer', 'distinct', Rule::exists('blog_posts', 'id')],
            'faqs' => ['sometimes', 'array', 'max:12'],
            'faqs.*' => ['array:question,answer'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:2000'],
            'references' => ['sometimes', 'array', 'max:20'],
            'references.*' => ['array:title,url'],
            'references.*.title' => ['required', 'string', 'max:255'],
            'references.*.url' => ['bail', 'required', 'string', 'max:2048', function ($field, $value, $fail) {
                if (! JournalMetadata::validHttps($value)) {
                    $fail('Referensi harus berupa URL HTTPS tanpa kredensial atau fragmen.');
                }
            }],
        ];
    }

    public function validate(BlogPost $post): void
    {
        Validator::make($post->only(['related_product_ids', 'related_article_ids', 'faqs', 'references']),
            collect(self::fields())->except('featured_media_id')->map(fn ($rules) => array_map(fn ($rule) => $rule === 'sometimes' ? 'nullable' : $rule, $rules))->all())->validate();
        foreach (['related_product_ids', 'related_article_ids'] as $field) {
            if ($post->{$field} !== null) {
                $post->{$field} = array_map('intval', $post->{$field});
            }
        }
        if (in_array($post->id, $post->related_article_ids ?? [], true)) {
            throw ValidationException::withMessages(['related_article_ids' => 'Artikel tidak dapat merekomendasikan dirinya sendiri.']);
        }
        preg_match_all('/data-qammaris-(media|gallery|article|product)="([0-9,]+)"/', $post->content, $markers, PREG_SET_ORDER);
        if (preg_match_all('/data-qammaris-(media|gallery|article|product|youtube|callout|cta)=/', $post->content) > 50) {
            throw ValidationException::withMessages(['content' => 'Maksimal 50 komponen per artikel.']);
        }
        foreach ($markers as $marker) {
            $ids = array_unique(array_map('intval', explode(',', $marker[2])));
            $count = match ($marker[1]) {
                'media', 'gallery' => $post->exists ? $post->media()->whereNull('archived_at')->whereIn('id', $ids)->count() : 0,
                'article' => BlogPost::whereKey($ids)->where('id', '!=', $post->id ?? 0)->count(),
                'product' => Product::whereKey($ids)->count(),
            };
            if ($count !== count($ids)) {
                throw ValidationException::withMessages(['content' => 'Komponen tidak tersedia atau bukan milik artikel ini. Pilih ulang melalui editor.']);
            }
        }
    }
}
