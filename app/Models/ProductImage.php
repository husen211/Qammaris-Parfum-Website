<?php

namespace App\Models;

use App\Services\ProductMediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use HasFactory, SoftDeletes;

    public const MAX_PER_PRODUCT = 3;

    public const PLACEHOLDER_URL = 'https://placehold.co/600x600/F5F5F5/333?text=No+Image';

    protected $fillable = [
        'product_id',
        'image_path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Logic URL Gambar yang Stabil
     */
    public function getImageUrlAttribute()
    {
        return app(ProductMediaStorage::class)->url($this->image_path) ?? self::PLACEHOLDER_URL;
    }
}
