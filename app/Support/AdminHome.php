<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AdminHome
{
    /** Where an account lands after login, or when it opens the admin login while already signed in. */
    public static function url(?User $user): string
    {
        if ($user && Gate::forUser($user)->allows('dashboard.view')) {
            return route('admin.dashboard');
        }
        if ($user && Gate::forUser($user)->allows('orders.manage')) {
            return route('admin.orders.index');
        }

        return route('home');
    }
}
