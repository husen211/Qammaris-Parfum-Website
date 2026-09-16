<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportRow extends Model
{
    public const APPLY_PENDING = 'pending';

    public const APPLY_CREATED = 'created';

    public const APPLY_UPDATED = 'updated';

    public const APPLY_BLOCKED_ERROR = 'blocked_error';

    public const APPLY_BLOCKED_PROTECTED = 'blocked_protected';

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
        'apply_status',
        'applied_product_id',
        'apply_message',
        'before_snapshot',
        'after_snapshot',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'normalized_data' => 'array',
            'issues' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'applied_at' => 'datetime',
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

    public function appliedProduct()
    {
        return $this->belongsTo(Product::class, 'applied_product_id');
    }
}
