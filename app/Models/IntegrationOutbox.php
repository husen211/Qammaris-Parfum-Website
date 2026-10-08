<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Website → App `order.changed` event, delivered with retries (contract §12). */
class IntegrationOutbox extends Model
{
    protected $table = 'integration_outbox';

    protected $fillable = ['event_id', 'online_order_id', 'revision', 'type', 'body', 'attempts', 'first_attempt_at', 'last_attempt_at',
        'next_attempt_at', 'last_status', 'last_error', 'delivered_at', 'failed_at'];

    protected $casts = [
        'first_attempt_at' => 'datetime', 'last_attempt_at' => 'datetime', 'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime', 'failed_at' => 'datetime', 'attempts' => 'integer', 'revision' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'online_order_id');
    }
}
