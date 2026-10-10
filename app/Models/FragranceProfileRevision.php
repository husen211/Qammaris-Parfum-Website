<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FragranceProfileRevision extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['evidence' => 'array', 'revision' => 'integer'];
}
