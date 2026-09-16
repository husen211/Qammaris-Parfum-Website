<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportBatch extends Model
{
    public const STATUS_PREVIEWED = 'previewed';

    protected $fillable = [
        'actor_id',
        'source_filename',
        'source_size',
        'source_fingerprint',
        'contract_version',
        'catalog_state_fingerprint',
        'idempotency_key',
        'status',
        'total_rows',
        'valid_rows',
        'review_rows',
        'error_rows',
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
            'skipped_blank_rows' => 'array',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function rows()
    {
        return $this->hasMany(ProductImportRow::class, 'batch_id')->orderBy('line_number');
    }
}
