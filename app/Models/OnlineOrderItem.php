<?php

namespace App\Models;

use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Model;

class OnlineOrderItem extends Model
{
    protected $fillable = ['product_id', 'variant_id', 'brand_name', 'product_name', 'volume', 'unit_price', 'quantity'];

    protected $casts = [
        'volume' => 'integer',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function lineTotal(): string
    {
        return Rupiah::decimal(Rupiah::minorUnits($this->unit_price) * $this->quantity);
    }

    public function label(): string
    {
        return $this->product_name.($this->volume ? ' '.$this->volume.' ml' : '');
    }
}
