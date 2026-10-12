<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Repeat customer (ORD-02c). Contact data is visible only to admin accounts with order access (D13). */
class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'note', 'created_by'];

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->whereNull('archived_at')->orderByDesc('last_used_at')->orderByDesc('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(OnlineOrder::class)->latest('id');
    }
}
