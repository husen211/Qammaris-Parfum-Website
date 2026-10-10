<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FragranceQuizResult extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['browser_hash'];

    protected $casts = ['answers' => 'array', 'recommendations' => 'array', 'expires_at' => 'immutable_datetime'];
}
