<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ORD-04: one browser's "Pesanan Saya" list (key hash only; see CustomerOrderHistory). */
class GuestOrderHistory extends Model
{
    protected $fillable = ['key_hash', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function entries(): HasMany
    {
        return $this->hasMany(GuestOrderHistoryEntry::class);
    }
}
