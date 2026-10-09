<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An address an admin confirmed for reuse. Archived instead of deleted. */
class CustomerAddress extends Model
{
    public const TYPES = ['local' => 'local_delivery', 'intercity' => 'intercity'];

    protected $fillable = ['label', 'type', 'address', 'postcode', 'location_url', 'confirmed_by', 'last_used_at', 'archived_at'];

    protected $casts = ['last_used_at' => 'datetime', 'archived_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
