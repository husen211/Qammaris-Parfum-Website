<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestOrderHistoryEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['online_order_id', 'opened_at'];

    protected $casts = ['opened_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'online_order_id');
    }
}
