<?php

namespace App\Models;

use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OnlineOrderItem extends Model
{
    protected $fillable = ['product_id', 'variant_id', 'brand_name', 'product_name', 'volume', 'unit_price', 'quantity', 'weight_grams'];

    protected $casts = [
        'volume' => 'integer',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'weight_grams' => 'integer',
    ];

    protected static function booted(): void
    {
        // Stable public line ID (contract `line_id`); packing and issues refer to it.
        static::creating(fn (OnlineOrderItem $item) => $item->line_id ??= (string) Str::ulid());
    }

    /** Current catalog product, for the admin thumbnail only; the order keeps its own name/price snapshot. */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotal(): string
    {
        return Rupiah::decimal(Rupiah::minorUnits($this->unit_price) * $this->quantity);
    }

    public function label(): string
    {
        return $this->product_name.($this->volume ? ' '.$this->volume.' ml' : '');
    }
}
