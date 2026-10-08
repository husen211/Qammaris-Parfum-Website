<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class BlogAutomationFields
{
    public const EDITORIAL = ['title', 'subtitle', 'excerpt', 'content', 'category', 'author', 'meta_description', 'seo_title', 'canonical_url', 'seo_indexable', 'seo_followable', 'og_title', 'og_description', 'og_image_url', 'featured_image_alt', 'featured_media_id', 'tag_ids', 'related_product_ids', 'related_article_ids', 'faqs', 'references'];

    public static function rejectUnknown(array $data, array $allowed): void
    {
        $errors = [];
        foreach (array_diff(array_keys($data), $allowed) as $field) {
            // Return useful field errors while bounding arbitrary client keys.
            $key = is_string($field) && preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]{0,63}\z/D', $field) ? $field : 'fields';
            $errors[$key][] = 'This operation does not allow this field.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function rules(): array
    {
        return array_filter(BlogEditorialRules::fields(), fn ($field) => in_array(explode('.', $field)[0], self::EDITORIAL, true), ARRAY_FILTER_USE_KEY);
    }
}
