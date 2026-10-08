<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Support\Str;

final class JournalMetadata
{
    public static function validHttps(?string $url): bool
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user'])
            && ! isset($parts['pass']) && ! isset($parts['fragment']);
    }

    public static function canonical(BlogPost $post): string
    {
        return self::validHttps($post->canonical_url) ? $post->canonical_url : route('blog.show', $post->slug);
    }

    public static function selfCanonical(BlogPost $post): bool
    {
        return self::canonical($post) === route('blog.show', $post->slug);
    }

    public static function robots(BlogPost $post): string
    {
        return ($post->seo_indexable ? 'index' : 'noindex').','.($post->seo_followable ? 'follow' : 'nofollow');
    }

    public static function schemas(BlogPost $post): array
    {
        $url = route('blog.show', $post->slug);
        $author = $post->author ?: 'Qammaris Editorial';
        $schema = [
            '@context' => 'https://schema.org', '@type' => 'BlogPosting',
            'headline' => $post->title, 'description' => $post->excerpt,
            'mainEntityOfPage' => $url, 'url' => $url,
            'author' => ['@type' => $author === 'Qammaris Editorial' ? 'Organization' : 'Person', 'name' => $author],
            'publisher' => ['@type' => 'Organization', 'name' => 'Qammaris Perfumes'],
        ];
        if ($post->featured_image) {
            $schema['image'] = $post->featured_image_url;
        }
        if ($post->published_at) {
            $schema['datePublished'] = $post->published_at->toAtomString();
        }
        if ($post->content_updated_at) {
            $schema['dateModified'] = $post->content_updated_at->toAtomString();
        }
        $crumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Qammaris Journal', 'item' => route('blog.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $post->category, 'item' => route('blog.category', Str::slug($post->category))],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => $url],
        ];

        return [$schema, ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs]];
    }
}
