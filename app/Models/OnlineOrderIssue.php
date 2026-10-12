<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A problem that blocks completion until resolved (contract `issues[]`). */
class OnlineOrderIssue extends Model
{
    public const TYPES = [
        'stock_problem' => 'Stok bermasalah',
        'courier_problem' => 'Kendala kurir',
        'customer_unreachable' => 'Customer tidak bisa dihubungi',
        'address_problem' => 'Alamat bermasalah',
        'payment_problem' => 'Kendala pembayaran',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'type', 'note', 'line_id', 'reported_quantity', 'status', 'opened_by_user_id', 'opened_by_app_user_id', 'opened_by_name',
        'resolved_by_user_id', 'resolved_by_app_user_id', 'resolution_note', 'resolved_at',
    ];

    protected $casts = ['reported_quantity' => 'integer', 'resolved_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (OnlineOrderIssue $issue) => $issue->public_id ??= (string) Str::ulid());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OnlineOrder::class, 'online_order_id');
    }
}
