<?php

namespace App\Actions\Blog;

use App\Exceptions\BlogPostConflict;
use App\Models\BlogAutomationActor;
use App\Models\BlogMedia;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogImageProcessor;
use App\Services\BlogMediaStorage;
use App\Support\BlogWriteAccess;
use App\Support\JournalMetadata;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveBlogMedia
{
    public function __construct(private BlogMediaStorage $storage, private BlogImageProcessor $processor, private RecordBlogPostChange $audit) {}

    public static function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            'alt' => ['required', 'string', 'max:255'], 'caption' => ['nullable', 'string', 'max:2000'],
            'credit' => ['nullable', 'string', 'max:255'], 'license' => ['required', 'string', 'max:255'],
            'source_url' => ['bail', 'nullable', 'string', 'max:2048', function ($field, $value, $fail) {
                if (! JournalMetadata::validHttps($value)) {
                    $fail('Sumber harus URL HTTPS lengkap tanpa kredensial atau fragmen.');
                }
            }],
            'crop' => ['required', Rule::in(['original', '16:9', '4:3', '1:1'])],
            'focal_x' => ['required', 'integer', 'between:0,100'], 'focal_y' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function handle(BlogPost $post, User|BlogAutomationActor $actor, array $data, ?UploadedFile $image = null, ?int $mediaId = null, bool $archive = false, ?Closure $afterSave = null): BlogMedia
    {
        BlogWriteAccess::assert($actor, $post);
        abort_if($actor instanceof BlogAutomationActor && ($mediaId !== null || $archive), 403);
        Validator::make($data, $archive ? ['revision' => ['required', 'integer', 'min:1']] : self::rules())->validate();
        $existing = $mediaId ? $post->media()->findOrFail($mediaId) : null;
        if (! $existing && ! $image) {
            throw ValidationException::withMessages(['image' => 'Pilih gambar untuk diunggah.']);
        }
        $path = null;
        $prepared = null;
        try {
            if ($image) {
                $path = $this->storage->store($image);
                $prepared = $this->processor->prepare($image, $this->storage->diskName(), $path, $data);
            } elseif (! $archive && ($existing->crop !== $data['crop'] || $existing->focal_x !== (int) $data['focal_x'] || $existing->focal_y !== (int) $data['focal_y'])) {
                $bytes = Storage::disk($existing->disk)->get($existing->path);
                if (hash('sha256', $bytes) !== $existing->checksum) {
                    throw new \RuntimeException('Original image checksum changed');
                }
                $prepared = $this->processor->prepareBytes($bytes, $existing->disk, $existing->path, $data);
            }

            return DB::transaction(function () use ($post, $actor, $data, $existing, $archive, $prepared, $afterSave) {
                $post = BlogPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
                BlogWriteAccess::assert($actor, $post, lock: true);
                if ($post->revision !== (int) $data['revision']) {
                    throw new BlogPostConflict;
                }
                if ($post->archived_at) {
                    throw ValidationException::withMessages(['revision' => 'Pulihkan artikel sebelum mengelola media.']);
                }
                $before = $this->audit->snapshot($post);
                $media = $existing ? $post->media()->whereKey($existing->id)->lockForUpdate()->firstOrFail() : new BlogMedia(['blog_post_id' => $post->id]);
                if ($media->archived_at) {
                    throw ValidationException::withMessages(['image' => 'Media ini sudah diarsipkan. Unggah media baru.']);
                }
                if ($archive) {
                    if ($post->featured_media_id === $media->id) {
                        throw ValidationException::withMessages(['image' => 'Pilih gambar utama pengganti sebelum mengarsipkan gambar ini.']);
                    }
                    $media->archived_at = now();
                } else {
                    $media->fill(collect($data)->only(['alt', 'caption', 'credit', 'source_url', 'license', 'crop', 'focal_x', 'focal_y'])->all());
                    if ($prepared) {
                        $media->fill($prepared);
                    }
                }
                if ($media->exists && ! $media->isDirty()) {
                    $afterSave?->__invoke($media);

                    return $media;
                }
                $creating = ! $media->exists;
                $media->save();
                if ($post->featured_media_id === $media->id) {
                    $post->featured_image_alt = $media->alt;
                }
                $post->revision++;
                $post->content_updated_at = now();
                $post->save();
                $this->audit->handle($post, $actor, $archive ? 'media_archived' : ($creating ? 'media_uploaded' : 'media_updated'), $before);
                $afterSave?->__invoke($media);
                DB::afterCommit(function () {
                    try {
                        cache()->forget('sitemap.xml');
                    } catch (Throwable $error) {
                        Log::warning('blog.sitemap_invalidation_failed', ['exception' => $error::class]);
                    }
                });

                return $media;
            }, attempts: 3);
        } catch (Throwable $error) {
            if ($prepared) {
                $this->processor->discard($prepared);
            }
            if ($path) {
                $this->storage->discard($path);
            }
            throw $error;
        }
    }
}
