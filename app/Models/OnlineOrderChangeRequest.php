<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Customer data change that needs admin review (after payment or once packing started). */
class OnlineOrderChangeRequest extends Model
{
    protected $fillable = ['changes', 'source', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected $casts = ['changes' => 'array', 'reviewed_at' => 'datetime'];
}
