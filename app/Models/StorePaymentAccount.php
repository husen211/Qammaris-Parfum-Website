<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Official store bank account (ORD-04). Only verified, active accounts reach payment instructions or customers. */
class StorePaymentAccount extends Model
{
    protected $fillable = ['bank_name', 'account_number', 'account_holder', 'created_by'];

    protected $casts = ['verified_at' => 'datetime', 'deactivated_at' => 'datetime'];

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at')->whereNull('deactivated_at')->orderBy('id');
    }

    public function isUsable(): bool
    {
        return $this->verified_at !== null && $this->deactivated_at === null;
    }

    public function line(): string
    {
        return $this->bank_name.' '.$this->account_number.' a.n. '.$this->account_holder;
    }
}
