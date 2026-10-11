<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BlogMedia extends Model
{
    protected $table = 'blog_media';

    protected $guarded = ['id'];

    protected $casts = ['blog_post_id' => 'integer', 'variants' => 'array', 'archived_at' => 'datetime', 'width' => 'integer', 'height' => 'integer', 'focal_x' => 'integer', 'focal_y' => 'integer'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function sources(): array
    {
        return collect($this->variants ?? [])->where('crop', $this->crop)->sortBy('width')->values()->all();
    }

    public function srcset(): string
    {
        return collect($this->sources())->map(fn ($variant) => Storage::disk($this->disk)->url($variant['path']).' '.$variant['width'].'w')->implode(', ');
    }
}
