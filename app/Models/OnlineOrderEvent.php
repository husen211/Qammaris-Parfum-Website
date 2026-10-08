<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OnlineOrderEvent extends Model
{
    public const UPDATED_AT = null;

    public const KINDS = [
        'created', 'advance', 'revert', 'cancel', 'details_updated', 'link_regenerated', 'advance_recorded', 'reimbursed',
    ];

    protected $fillable = ['kind', 'stage', 'actor_type', 'source', 'actor_user_id', 'actor_app_user_id', 'actor_display_name', 'staff_name', 'note', 'idempotency_key', 'request_id'];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        // Public event ID (contract `event_id`).
        static::creating(fn (OnlineOrderEvent $event) => $event->public_id ??= (string) Str::ulid());
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorLabel(): string
    {
        return match ($this->actor_type) {
            'customer' => 'Customer',
            'staff' => ($this->staff_name ?: 'Staf').' (staf)',
            'app' => ($this->actor_display_name ?: 'Karyawan').' (App)',
            default => $this->actor?->name ?? 'Admin',
        };
    }
}
