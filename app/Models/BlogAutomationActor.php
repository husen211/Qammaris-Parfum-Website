<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class BlogAutomationActor extends Authenticatable
{
    use HasApiTokens;

    public const ABILITIES = ['blog:read', 'blog:write', 'blog:media', 'catalog:read'];

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
