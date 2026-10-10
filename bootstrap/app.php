<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BlogAutomationAccess;
use App\Http\Middleware\BlogAutomationEnabled;
use App\Http\Middleware\FragranceBrowser;
use App\Support\BlogAutomationErrors;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware([])->group(__DIR__.'/../routes/sitemap.php');
            Route::middleware([])->group(__DIR__.'/../routes/integrations.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList(ThrottleRequests::class, FragranceBrowser::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, BlogAutomationEnabled::class);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'blog.automation.enabled' => BlogAutomationEnabled::class,
            'blog.automation.access' => BlogAutomationAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/automation/v1*') || $request->expectsJson());
        $exceptions->render(fn (Throwable $error, Request $request) => BlogAutomationErrors::render($error, $request));
        $exceptions->report(function (Throwable $error) {
            if (request()->is('api/automation/v1*')) {
                Log::error('blog.automation_failed', ['exception' => $error::class, 'code' => (string) $error->getCode()]);

                return false;
            }
        });
    })->create();
