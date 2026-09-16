<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductExternalIdentity extends Model
{
    public const PROVIDER_SHOPEE = 'shopee';

    public const PROVIDER_MAJOO = 'majoo';

    public const SUPPORTED_PROVIDERS = [
        self::PROVIDER_SHOPEE,
        self::PROVIDER_MAJOO,
    ];

    protected $fillable = [
        'provider',
        'external_product_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
