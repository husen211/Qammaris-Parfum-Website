<?php

namespace App\Actions\Blog;

use App\Exceptions\BlogPostConflict;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\BlogImageProcessor;
use App\Services\BlogMediaStorage;
use App\Support\BlogComponentRules;
use App\Support\BlogHtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveBlogPost
{
    private const EDITORIAL_FIELDS = ['title', 'excerpt', 'content', 'category_id', 'category', 'author', 'featured_image', 'featured_image_disk', 'subtitle', 'featured_image_alt', 'is_featured', 'featured_media_id', 'related_product_ids', 'related_article_ids', 'faqs', 'references'];

    public function __construct(
        private BlogHtmlSanitizer $sanitizer,
        private BlogMediaStorage $storage,
        private RecordBlogPostChange $audit,
        private BlogImageProcessor $processor,
        private BlogComponentRules $components,
    ) {}

    public function handle(array $data, User $actor, ?BlogPost $post = null, ?UploadedFile $image = null): BlogPost
    {
        abort_unless($actor->exists && $actor->role === 'admin', 403);
        $path = null;
        $prepared = null;

        try {
            // File IO precedes the short DB transaction; failure never removes the current image.
            if ($image !== null) {
                $path = $this->storage->store($image);
                $prepared = $this->processor->prepare($image, $this->storage->diskName(), $path);
            }

            return DB::transaction(function () use ($data, $actor, $post, $path, $prepared): BlogPost {
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
                $wasPublished = $post->is_published && $post->published_at !== null;
                $oldCategory = $post->category;
                $category = BlogCategory::query()->where('name', $data['category'] ?? $oldCategory ?? 'Tips')->lockForUpdate()->first();
                if (! $category) {
                    throw ValidationException::withMessages(['category' => 'Pilih kategori jurnal yang tersedia.']);
                }
                $oldTags = $creating ? [] : $post->tags()->orderBy('blog_tags.id')->pluck('blog_tags.id')->all();
                $tagIds = array_key_exists('tag_ids', $data) ? array_values(array_unique(array_map('intval', $data['tag_ids']))) : $oldTags;
                sort($tagIds);
                $tags = BlogTag::query()->whereIn('id', $tagIds)->lockForUpdate()->get();
                if ($tags->count() !== count($tagIds) || $tags->contains(fn ($tag) => ! $tag->is_active && ! in_array($tag->id, $oldTags, true))) {
                    throw ValidationException::withMessages(['tag_ids' => 'Pilih tag aktif yang tersedia.']);
                }
                $publish = (bool) ($data['is_published'] ?? false);
                $date = $data['published_at'] ?? null;
                $post->fill([
                    'title' => $data['title'] ?? 'Draft tanpa judul', 'excerpt' => $data['excerpt'] ?? '',
                    'content' => $this->sanitizer->sanitize($data['content'] ?? ''),
                    'author' => $data['author'] ?? ($creating ? 'Qammaris Editorial' : $post->author),
                    'meta_description' => $data['meta_description'] ?? null,
                    'is_published' => $publish,
                    'published_at' => $publish ? ($date ? Carbon::parse($date) : ($post->published_at ?? now())) : $post->published_at,
                ]);
                foreach (['related_product_ids', 'related_article_ids', 'faqs', 'references'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $value = $data[$field] ?? [];
                        // An empty legacy field and an empty editor list mean the same thing.
                        if ($value !== [] || $post->{$field} !== null) {
                            $post->{$field} = $value;
                        }
                    }
                }
                $this->components->validate($post);
                // Retain the original enum column; new taxonomy does not rewrite legacy rows.
                if ($creating || $post->category_id !== null || $oldCategory !== $category->name) {
                    $post->category_id = $category->id;
                }
                $post->category = in_array($category->name, BlogPost::CATEGORY_OPTIONS, true) ? $category->name : $post->getRawOriginal('category') ?? 'Tips';
                $post->setRelation('editorialCategory', $category);
                foreach (['subtitle', 'featured_image_alt', 'is_featured', 'seo_title', 'canonical_url', 'seo_indexable', 'seo_followable', 'og_title', 'og_description', 'og_image_url'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $post->{$field} = $data[$field] ?? ($field === 'is_featured' ? false : null);
                    }
                }
                if ($path !== null) {
                    $post->featured_image = $path;
                    $post->featured_image_disk = $this->storage->diskName();
                } elseif (! empty($data['featured_media_id'])) {
                    $media = $post->exists ? $post->media()->whereNull('archived_at')->find($data['featured_media_id']) : null;
                    if (! $media) {
                        throw ValidationException::withMessages(['featured_media_id' => 'Pilih media aktif milik artikel ini.']);
                    }
                    $changingMedia = $post->featured_media_id !== $media->id;
                    $post->featured_media_id = $media->id;
                    $post->featured_image = $media->path;
                    $post->featured_image_disk = $media->disk;
                    if ($changingMedia) {
                        $post->featured_image_alt = $media->alt;
                    }
                }
                if ($publish) {
                    $errors = [];
                    foreach (['title' => 'judul', 'excerpt' => 'ringkasan', 'author' => 'penulis'] as $field => $label) {
                        if (trim((string) $post->{$field}) === '' || ($field === 'title' && $post->title === 'Draft tanpa judul')) {
                            $errors[$field] = "Lengkapi {$label} sebelum menerbitkan.";
                        }
                    }
                    if (! preg_match('/[\p{L}\p{N}]/u', html_entity_decode(strip_tags($post->content)))) {
                        $errors['content'] = 'Isi artikel harus mempunyai teks yang bermakna.';
                    }
                    if (! $category->is_active && (! $wasPublished || $oldCategory !== $category->name)) {
                        $errors['category'] = 'Pilih kategori aktif sebelum menerbitkan.';
                    }
                    if (! $wasPublished) {
                        if (! $post->featured_image) {
                            $errors['featured_image'] = 'Tambahkan gambar utama sebelum menerbitkan.';
                        }
                        if (trim((string) $post->featured_image_alt) === '') {
                            $errors['featured_image_alt'] = 'Jelaskan gambar utama pada teks alternatif.';
                        }
                    }
                    if ($errors !== []) {
                        throw ValidationException::withMessages($errors);
                    }
                }
                $tagsChanged = $oldTags !== $tagIds;

                if ($creating || $tagsChanged || $post->isDirty(self::EDITORIAL_FIELDS)) {
                    $post->content_updated_at = now();
                }
                if (! $creating && ! $tagsChanged && ! $post->isDirty()) {
                    return $post;
                }

                $post->revision = $creating ? 1 : $post->revision + 1;
                $post->save();
                if ($prepared !== null) {
                    $media = $post->media()->create($prepared + ['alt' => $post->featured_image_alt ?? '', 'license' => 'Milik Qammaris']);
                    $post->featured_media_id = $media->id;
                    $post->save();
                } elseif ($post->featured_media_id) {
                    $featured = $post->media()->whereKey($post->featured_media_id)->whereNull('archived_at')->first();
                    if ($featured && $featured->alt !== ($post->featured_image_alt ?? '')) {
                        $featured->alt = $post->featured_image_alt ?? '';
                        $featured->save();
                    }
                }
                if ($tagsChanged) {
                    $post->tags()->sync($tagIds);
                }
                $post->unsetRelation('tags');
                $this->audit->handle($post, $actor, $creating ? 'created' : 'updated', $before);
                $this->invalidateSitemap();

                return $post;
            });
        } catch (Throwable $error) {
            if ($prepared !== null) {
                $this->processor->discard($prepared);
            }
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
