<?php

namespace App\Actions\Blog;

use App\Exceptions\BlogPostConflict;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogMediaStorage;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveBlogPost
{
    private const EDITORIAL_FIELDS = ['title', 'excerpt', 'content', 'category', 'author', 'featured_image', 'featured_image_disk'];

    public function __construct(
        private BlogHtmlSanitizer $sanitizer,
        private BlogMediaStorage $storage,
        private RecordBlogPostChange $audit,
    ) {}

    public function handle(array $data, User $actor, ?BlogPost $post = null, ?UploadedFile $image = null): BlogPost
    {
        abort_unless($actor->exists && $actor->role === 'admin', 403);
        $path = null;

        try {
            // File IO precedes the short DB transaction; failure never removes the current image.
            if ($image !== null) {
                $path = $this->storage->store($image);
            }

            return DB::transaction(function () use ($data, $actor, $post, $path): BlogPost {
                $creating = $post === null;
                if (! $creating) {
                    $post = BlogPost::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();
                    if ($post->revision !== (int) ($data['revision'] ?? 0)) {
                        throw new BlogPostConflict;
                    }
                    if ($post->archived_at !== null) {
                        throw ValidationException::withMessages(['revision' => 'Artikel diarsipkan. Pulihkan sebagai draft sebelum mengedit.']);
                    }
                }

                $before = $creating ? [] : $this->audit->snapshot($post);
                $post ??= new BlogPost;
                $publish = (bool) ($data['is_published'] ?? false);
                $date = $data['published_at'] ?? null;
                $post->fill([
                    'title' => $data['title'], 'excerpt' => $data['excerpt'],
                    'content' => $this->sanitizer->sanitize($data['content']), 'category' => $data['category'],
                    'author' => $data['author'] ?? ($creating ? 'Qammaris Editorial' : $post->author),
                    'meta_description' => $data['meta_description'] ?? null,
                    'is_published' => $publish,
                    'published_at' => $publish ? ($date ? Carbon::parse($date) : ($post->published_at ?? now())) : $post->published_at,
                ]);
                if ($path !== null) {
                    $post->featured_image = $path;
                    $post->featured_image_disk = $this->storage->diskName();
                }

                if ($creating || $post->isDirty(self::EDITORIAL_FIELDS)) {
                    $post->content_updated_at = now();
                }
                if (! $creating && ! $post->isDirty()) {
                    return $post;
                }

                $post->revision = $creating ? 1 : $post->revision + 1;
                $post->save();
                $this->audit->handle($post, $actor, $creating ? 'created' : 'updated', $before);
                $this->invalidateSitemap();

                return $post;
            });
        } catch (Throwable $error) {
            if ($path !== null) {
                $this->storage->discard($path);
            }
            throw $error;
        }
    }

    private function invalidateSitemap(): void
    {
        DB::afterCommit(function () {
            try {
                cache()->forget('sitemap.xml');
            } catch (Throwable $error) {
                // A cache outage after commit must not compensate a successfully attached file.
                Log::warning('blog.sitemap_invalidation_failed', ['exception' => $error::class]);
            }
        });
    }
}
