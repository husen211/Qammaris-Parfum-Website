<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Active task claim; the unique (order, task) key makes claiming atomic. Releasing deletes the row; events keep history. */
class OnlineOrderClaim extends Model
{
    public const TASKS = ['preparation', 'courier_booking', 'handover'];

    public $timestamps = false;

    protected $fillable = ['task', 'holder_app_user_id', 'holder_display_name', 'claimed_at'];

    protected $casts = ['claimed_at' => 'datetime'];
}
