<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineOrderEvent extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'created', 'advance', 'revert', 'cancel', 'details_updated', 'link_regenerated', 'advance_recorded', 'reimbursed',
    ];

    protected $fillable = ['kind', 'stage', 'actor_type', 'actor_user_id', 'staff_name', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorLabel(): string
    {
        return match ($this->actor_type) {
            'customer' => 'Customer',
            'staff' => ($this->staff_name ?: 'Staf').' (staf)',
            default => $this->actor?->name ?? 'Admin',
        };
    }
}
