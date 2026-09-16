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

    public const IMAGE_QUEUED = 'queued';

    public const IMAGE_PROCESSING = 'processing';

    public const IMAGE_COMPLETED = 'completed';

    public const IMAGE_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const IMAGE_NO_SOURCES = 'no_sources';

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
        'image_acquisition_status',
        'image_acquisition_requested_by',
        'image_acquisition_requested_at',
        'image_acquisition_completed_at',
        'image_acquisition_outcomes',
        'resolution_status',
        'resolution_fields',
        'resolved_by',
        'resolved_at',
        'resolution_message',
        'resolution_before_snapshot',
        'resolution_after_snapshot',
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
            'image_acquisition_requested_at' => 'datetime',
            'image_acquisition_completed_at' => 'datetime',
            'image_acquisition_outcomes' => 'array',
            'resolution_fields' => 'array',
            'resolved_at' => 'datetime',
            'resolution_before_snapshot' => 'array',
            'resolution_after_snapshot' => 'array',
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

    public function imageAcquisitionRequestedBy()
    {
        return $this->belongsTo(User::class, 'image_acquisition_requested_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
