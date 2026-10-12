<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Price change after the order was agreed. Counts toward the total only once a Super Admin approves it. */
class OnlineOrderAdjustment extends Model
{
    protected $fillable = ['amount', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note'];

    protected $casts = ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
}
