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
        return $post->only(['title', 'slug', 'author', 'category', 'is_published', 'featured_image_disk']) + [
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
        if (! in_array($action, ['created', 'updated', 'archived', 'restored'], true)
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
