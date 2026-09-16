<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Product extends Model
{
    use HasFactory, HasSlug;

    public const PUBLICATION_DRAFT = 'draft';

    public const PUBLICATION_PUBLISHED = 'published';

    public const PUBLICATION_ARCHIVED = 'archived';

    public const AVAILABILITY_UNKNOWN = 'unknown';

    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_SOLD_OUT = 'sold_out';

    public const AVAILABILITY_FRESH_HOURS = 36;

    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'compare_at_price',
        'fragrance_notes',
        'gender',
        'is_best_seller',
        'is_active',
        'publication_status',
        'published_at',
        'archived_at',
        'availability_status',
        'stock_quantity',
        'availability_source',
        'availability_checked_at',
        'view_count',
        'meta_description',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'fragrance_notes' => 'array',
        'is_best_seller' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'stock_quantity' => 'integer',
        'availability_checked_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Relationship: Product belongs to Brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relationship: Product belongs to Category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relationship: Product has many Variants
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('volume');
    }

    /**
     * Relationship: Product has many Images
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get primary image
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Accessor: Formatted price in Rupiah
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp '.number_format($this->base_price, 0, ',', '.');
    }

    /**
     * Accessor: Get cheapest variant price
     */
    public function getCheapestPriceAttribute()
    {
        $minPrice = $this->getAttribute('variants_min_price');
        if ($minPrice !== null) {
            return $minPrice;
        }

        if ($this->relationLoaded('variants')) {
            return $this->variants->min('price') ?? $this->base_price;
        }

        return $this->variants()->min('price') ?? $this->base_price;
    }

    /**
     * Accessor: Get most expensive variant price
     */
    public function getMostExpensivePriceAttribute()
    {
        $maxPrice = $this->getAttribute('variants_max_price');
        if ($maxPrice !== null) {
            return $maxPrice;
        }

        if ($this->relationLoaded('variants')) {
            return $this->variants->max('price') ?? $this->base_price;
        }

        return $this->variants()->max('price') ?? $this->base_price;
    }

    /**
     * Accessor: Price range display
     */
    public function getPriceRangeAttribute(): string
    {
        $cheapest = $this->cheapest_price;
        $mostExpensive = $this->most_expensive_price;

        if ($cheapest == $mostExpensive) {
            return 'Rp '.number_format($cheapest, 0, ',', '.');
        }

        return 'Rp '.number_format($cheapest, 0, ',', '.').' - Rp '.number_format($mostExpensive, 0, ',', '.');
    }

    /**
     * Scope: Active products
     */
    public function scopeActive($query)
    {
        return $this->scopePublished($query);
    }

    /**
     * Public visibility during the compatibility transition.
     */
    public function scopePublished($query)
    {
        return $query
            ->where('publication_status', self::PUBLICATION_PUBLISHED)
            ->where('is_active', true);
    }

    /**
     * Scope: Best sellers
     */
    public function scopeBestSellers($query)
    {
        return $query->where('is_best_seller', true);
    }

    /**
     * Scope: Filter by brand
     */
    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope: Search products
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('brand', function ($brandQuery) use ($term) {
                    $brandQuery->where('name', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    public function isPublished(): bool
    {
        return $this->publication_status === self::PUBLICATION_PUBLISHED && $this->is_active;
    }

    public function markArchived(): void
    {
        if ($this->publication_status === self::PUBLICATION_ARCHIVED && ! $this->is_active) {
            return;
        }

        $this->update([
            'publication_status' => self::PUBLICATION_ARCHIVED,
            'archived_at' => now(),
            'is_active' => false,
        ]);
    }

    public function markPublished(): void
    {
        if ($this->publication_status === self::PUBLICATION_PUBLISHED && $this->is_active) {
            return;
        }

        $this->update([
            'publication_status' => self::PUBLICATION_PUBLISHED,
            'published_at' => $this->published_at ?? now(),
            'archived_at' => null,
            'is_active' => true,
        ]);
    }

    public function getEffectiveAvailabilityAttribute(): string
    {
        $status = $this->availability_status ?? self::AVAILABILITY_UNKNOWN;

        if ($status === self::AVAILABILITY_AVAILABLE) {
            if (! $this->availability_checked_at) {
                return self::AVAILABILITY_UNKNOWN;
            }

            if ($this->availability_checked_at->lt(now()->subHours(self::AVAILABILITY_FRESH_HOURS))) {
                return self::AVAILABILITY_UNKNOWN;
            }
        }

        if (! in_array($status, [
            self::AVAILABILITY_UNKNOWN,
            self::AVAILABILITY_AVAILABLE,
            self::AVAILABILITY_SOLD_OUT,
        ], true)) {
            return self::AVAILABILITY_UNKNOWN;
        }

        return $status;
    }
}
