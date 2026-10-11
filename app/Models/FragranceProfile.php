<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FragranceProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['derived' => 'array', 'overrides' => 'array', 'revision' => 'integer'];
}
