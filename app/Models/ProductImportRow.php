<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportRow extends Model
{
    protected $fillable = [
        'line_number',
        'status',
        'candidate_action',
        'provider',
        'external_product_id',
        'matched_product_id',
        'normalized_data',
        'issues',
        'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'normalized_data' => 'array',
            'issues' => 'array',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(ProductImportBatch::class, 'batch_id');
    }

    public function matchedProduct()
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }
}
