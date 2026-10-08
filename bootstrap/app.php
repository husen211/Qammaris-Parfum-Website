<?php

use App\Exceptions\OnlineOrderRejected;
use App\Exceptions\OrderApi\OrderApiException;
use App\Http\Middleware\AdminMiddleware;
use App\Support\AdminHome;
use App\Support\OrderApi\OrderApiResponse;
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
        // Expected order rejections are answered to the caller, not written to the error log.
        $exceptions->dontReport([OnlineOrderRejected::class, OrderApiException::class]);
        // Order API v1 always answers with the contract error envelope (ORD-02e).
        $exceptions->render(function (Throwable $error, Request $request) {
            if ($request->is(OrderApiResponse::PATH, OrderApiResponse::PATH.'/*')) {
                return OrderApiResponse::fromThrowable($request, $error);
            }
        });
    })->create();
