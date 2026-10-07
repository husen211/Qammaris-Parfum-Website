<?php

namespace App\Actions\Blog;

use App\Exceptions\BlogPostConflict;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChangeBlogPostArchive
{
    public function __construct(private RecordBlogPostChange $audit) {}

    public function handle(BlogPost $post, int $revision, bool $archive, User $actor): BlogPost
    {
        abort_unless($actor->exists && $actor->role === 'admin', 403);

        return DB::transaction(function () use ($post, $revision, $archive, $actor): BlogPost {
            $post = BlogPost::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();
            if ($post->revision !== $revision) {
                throw new BlogPostConflict;
            }
            if (($post->archived_at !== null) === $archive) {
                return $post;
            }

            $before = $this->audit->snapshot($post);
            $post->archived_at = $archive ? now() : null;
            if (! $archive) {
                // Recovery retains content/media/dates but never republishes accidentally.
                $post->is_published = false;
            }
            $post->revision++;
            $post->save();
            $this->audit->handle($post, $actor, $archive ? 'archived' : 'restored', $before);
            DB::afterCommit(function () {
                try {
                    cache()->forget('sitemap.xml');
                } catch (Throwable $error) {
                    Log::warning('blog.sitemap_invalidation_failed', ['exception' => $error::class]);
                }
            });

            return $post;
        });
    }
}
