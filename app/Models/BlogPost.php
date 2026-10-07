<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class BlogPost extends Model
{
    use HasFactory, HasSlug;

    public const CATEGORY_OPTIONS = ['Tips', 'Review', 'Panduan', 'Berita'];

    protected $attributes = ['seo_indexable' => true, 'seo_followable' => true];

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'author',
        'category',
        'is_published',
        'published_at',
        'view_count',
        'meta_description',
        'subtitle',
        'featured_image_alt',
        'is_featured',
        'seo_title',
        'canonical_url',
        'seo_indexable',
        'seo_followable',
        'og_title',
        'og_description',
        'og_image_url',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'revision' => 'integer',
        'archived_at' => 'datetime',
        'content_updated_at' => 'datetime',
        'is_featured' => 'boolean',
        'seo_indexable' => 'boolean',
        'seo_followable' => 'boolean',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isPubliclyVisible(): bool
    {
        return $this->archived_at === null && $this->is_published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * Accessor: Formatted published date
     */
    public function getPublishedDateAttribute(): string
    {
        return $this->published_at?->translatedFormat('d F Y') ?? '-';
    }

    /**
     * Accessor: Reading time estimate
     */
    public function getReadingTimeAttribute(): string
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $minutes = max(1, (int) ceil($wordCount / 200));

        return $minutes.' menit';
    }

    /**
     * Accessor: Featured image URL
     */
    public function getFeaturedImageUrlAttribute(): string
    {
        if ($this->featured_image && $this->featured_image_disk) {
            return Storage::disk($this->featured_image_disk)->url($this->featured_image);
        }
        if (! $this->featured_image) {
            return asset('images/about-section.jpg');
        }

        if (Str::startsWith($this->featured_image, ['http://', 'https://'])) {
            return $this->featured_image;
        }

        if (Str::startsWith($this->featured_image, '/')) {
            return $this->featured_image;
        }

        if (Str::startsWith($this->featured_image, 'storage/')) {
            return asset($this->featured_image);
        }

        if (Str::startsWith($this->featured_image, 'images/')) {
            return asset($this->featured_image);
        }

        return asset('images/'.$this->featured_image);
    }

    /**
     * Scope: Published posts
     */
    public function scopePublished($query)
    {
        return $query->whereNull('archived_at')->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, $category)
    {
        $normalized = str_replace('-', ' ', Str::lower($category));

        return $query->where(function ($query) use ($category, $normalized) {
            $query->whereHas('editorialCategory', fn ($taxonomy) => $taxonomy->where('slug', Str::slug($category)))
                ->orWhere(fn ($legacy) => $legacy->whereNull('category_id')->whereRaw('lower(category) = ?', [$normalized]));
        });
    }

    public function editorialCategory(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function getCategoryAttribute(?string $value): ?string
    {
        return $this->category_id ? $this->editorialCategory?->name : $value;
    }

    /**
     * Scope: Latest first
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    /**
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }
}
