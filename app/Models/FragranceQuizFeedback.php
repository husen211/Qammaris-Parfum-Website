<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FragranceQuizFeedback extends Model
{
    protected $table = 'fragrance_quiz_feedback';

    protected $guarded = ['id'];

    protected $casts = ['products' => 'array'];
}
