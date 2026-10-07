<?php

namespace App\Actions\Blog;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class RecordBlogPostChange
{
    public function snapshot(BlogPost $post): array
    {
        return $post->only(['title', 'slug', 'author', 'category', 'category_id', 'is_featured', 'is_published', 'featured_image_disk', 'seo_indexable', 'seo_followable']) + [
            'featured_media_id' => $post->featured_media_id,
            'related_product_ids' => $post->related_product_ids ?? [],
            'related_article_ids' => $post->related_article_ids ?? [],
            'faqs_hash' => $this->hash(json_encode($post->faqs ?? [], JSON_THROW_ON_ERROR)),
            'references_hash' => $this->hash(json_encode($post->references ?? [], JSON_THROW_ON_ERROR)),
            'media_hash' => $this->hash($post->exists ? json_encode($post->media()->orderBy('id')->get()->map(fn ($media) => $media->getAttributes())->all(), JSON_THROW_ON_ERROR) : '[]'),
            'canonical_hash' => $this->hash($post->canonical_url),
            'og_title_hash' => $this->hash($post->og_title),
            'og_description_hash' => $this->hash($post->og_description),
            'og_image_hash' => $this->hash($post->og_image_url),
            'tag_ids' => $post->exists ? $post->tags()->orderBy('blog_tags.id')->pluck('blog_tags.id')->all() : [],
            'subtitle_hash' => $this->hash($post->subtitle),
            'featured_image_alt_hash' => $this->hash($post->featured_image_alt),
            'seo_title_hash' => $this->hash($post->seo_title),
            'published_at' => $post->published_at?->toAtomString(),
            'archived_at' => $post->archived_at?->toAtomString(),
            'excerpt_hash' => $this->hash($post->excerpt),
            'content_hash' => $this->hash($post->content),
            'meta_description_hash' => $this->hash($post->meta_description),
            'image_key_hash' => $this->hash($post->featured_image),
        ];
    }

    public function handle(BlogPost $post, User $actor, string $action, array $before): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Blog history must commit with the article mutation.');
        }
        if (! in_array($action, ['created', 'updated', 'archived', 'restored', 'media_uploaded', 'media_updated', 'media_archived'], true)
            || ! $actor->exists || $actor->role !== 'admin') {
            throw new InvalidArgumentException('An authenticated admin and known blog action are required.');
        }

        $after = $this->snapshot($post);
        $fields = array_keys(array_filter($after, fn ($value, $field) => ! array_key_exists($field, $before) || $before[$field] !== $value, ARRAY_FILTER_USE_BOTH));
        if ($fields === []) {
            return;
        }

        DB::table('blog_post_changes')->insert([
            'blog_post_id' => $post->getKey(), 'actor_type' => 'admin', 'actor_id' => $actor->getKey(),
            'action' => $action, 'revision' => $post->revision,
            'changed_fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'before' => json_encode(array_intersect_key($before, array_flip($fields)), JSON_THROW_ON_ERROR),
            'after' => json_encode(array_intersect_key($after, array_flip($fields)), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    private function hash(?string $value): ?string
    {
        return $value === null ? null : hash('sha256', $value);
    }
}
