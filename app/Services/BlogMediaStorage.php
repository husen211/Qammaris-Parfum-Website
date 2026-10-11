<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class BlogMediaStorage
{
    public const UPLOAD_RULES = ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'extensions:jpeg,png,jpg,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'];

    public function diskName(): string
    {
        return config('media.blog_disk', 'public');
    }

    public function store(UploadedFile $file): string
    {
        Validator::make(['image' => $file], ['image' => self::UPLOAD_RULES])->validate();
        $disk = Storage::disk($this->diskName());
        $path = null;

        try {
            $path = $file->store('blog', $this->diskName());
            if (! is_string($path) || ! str_starts_with($path, 'blog/') || ! $disk->exists($path)
                || $disk->size($path) !== $file->getSize()) {
                throw new RuntimeException('Gambar artikel gagal diverifikasi di penyimpanan.');
            }

            return $path;
        } catch (Throwable $error) {
            if (is_string($path) && str_starts_with($path, 'blog/')) {
                $this->discard($path);
            }
            throw $error;
        }
    }

    /** Only the new path returned by store(), never an existing article's path. */
    public function discard(string $path): void
    {
        try {
            if (! Storage::disk($this->diskName())->delete($path)) {
                throw new RuntimeException('Blog upload cleanup failed.');
            }
        } catch (Throwable $error) {
            Log::error('blog.upload_cleanup_failed', ['exception' => $error::class]);
        }
    }
}
