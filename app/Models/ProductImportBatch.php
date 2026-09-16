<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportBatch extends Model
{
    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_STALE = 'stale';

    public const STATUS_INVALID = 'invalid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'actor_id',
        'applied_by',
        'source_filename',
        'source_size',
        'source_fingerprint',
        'contract_version',
        'catalog_state_fingerprint',
        'idempotency_key',
        'status',
        'applied_at',
        'failed_at',
        'total_rows',
        'valid_rows',
        'review_rows',
        'error_rows',
        'applied_rows',
        'blocked_rows',
        'failure_message',
        'skipped_blank_rows',
    ];

    protected function casts(): array
    {
        return [
            'source_size' => 'integer',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'review_rows' => 'integer',
            'error_rows' => 'integer',
            'applied_rows' => 'integer',
            'blocked_rows' => 'integer',
            'skipped_blank_rows' => 'array',
            'applied_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function rows()
    {
        return $this->hasMany(ProductImportRow::class, 'batch_id')->orderBy('line_number');
    }
}
