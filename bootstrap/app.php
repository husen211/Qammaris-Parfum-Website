<?php

use App\Http\Middleware\AdminMiddleware;
use App\Support\AdminHome;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware([])->group(__DIR__.'/../routes/sitemap.php');
            Route::middleware([])->group(__DIR__.'/../routes/integrations.php');
            Route::middleware([])->group(__DIR__.'/../routes/admin-pwa.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
        // Keep the installed admin app inside its scope: admin pages send guests to /admin/login (ORD-02b).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') ? AdminHome::url($request->user()) : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
