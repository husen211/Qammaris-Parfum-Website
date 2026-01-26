<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'volume',
        'price',
        'sku',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'volume' => 'integer',
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship: Variant belongs to Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Accessor: Formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    /**
     * Accessor: Display name (volume + ml)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->volume . 'ml';
    }

    /**
     * Accessor: Full display (volume + price)
     */
    public function getFullDisplayAttribute(): string
    {
        return $this->display_name . ' - ' . $this->formatted_price;
    }

    /**
     * Check if in stock
     */
    public function isInStock(): bool
    {
        return $this->stock > 0 && $this->is_active;
    }

    /**
     * Get stock status text
     */
    public function getStockStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'Tidak Tersedia';
        }

        if ($this->stock == 0) {
            return 'Habis';
        }

        if ($this->stock <= 5) {
            return 'Stok Terbatas';
        }

        return 'Tersedia';
    }

    /**
     * Scope: Active variants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: In stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0)->where('is_active', true);
    }
}