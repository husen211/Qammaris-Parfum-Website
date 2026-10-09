<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only money ledger row. Mistakes are cancelled by a `reversal` row, never edited or deleted. */
class OnlineOrderPayment extends Model
{
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    public const UPDATED_AT = null;

    protected $fillable = ['type', 'amount', 'method', 'confirmation_source', 'reference', 'basis', 'reverses_id', 'note', 'recorded_by'];

    protected $casts = ['amount' => 'decimal:2', 'created_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'online_order_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
