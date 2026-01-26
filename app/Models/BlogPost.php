<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, HasSlug;

    public const CATEGORY_OPTIONS = ['Tips', 'Review', 'Panduan', 'Berita'];

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
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'view_count' => 'integer',
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
        $minutes = ceil($wordCount / 200); // Rata-rata 200 kata/menit
        return $minutes . ' menit';
    }

    /**
     * Accessor: Featured image URL
     */
    public function getFeaturedImageUrlAttribute(): string
    {
        if (!$this->featured_image) {
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

        return asset('images/' . $this->featured_image);
    }

    /**
     * Scope: Published posts
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, $category)
    {
        $normalized = str_replace('-', ' ', Str::lower($category));

        return $query->whereRaw('lower(category) = ?', [$normalized]);
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
