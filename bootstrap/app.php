<?php

use App\Exceptions\OnlineOrderRejected;
use App\Exceptions\OrderApi\OrderApiException;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BlogAutomationAccess;
use App\Http\Middleware\BlogAutomationEnabled;
use App\Http\Middleware\FragranceBrowser;
use App\Http\Middleware\NoIndexOutsideProduction;
use App\Support\AdminHome;
use App\Support\BlogAutomationErrors;
use App\Support\OrderApi\OrderApiResponse;
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
            Route::middleware([])->group(__DIR__.'/../routes/admin-pwa.php');
            Route::middleware([])->group(__DIR__.'/../routes/public-pwa.php');
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
        $middleware->append(NoIndexOutsideProduction::class);
        // Keep the installed admin app inside its scope: admin pages send guests to /admin/login (ORD-02b).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*') ? AdminHome::url($request->user()) : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected order rejections are answered to the caller, not written to the error log.
        $exceptions->dontReport([OnlineOrderRejected::class, OrderApiException::class]);
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/automation/v1*') || $request->expectsJson());
        // Order API v1 (ORD-02e) and the Journal automation API each answer with their own error envelope.
        $exceptions->render(function (Throwable $error, Request $request) {
            if ($request->is(OrderApiResponse::PATH, OrderApiResponse::PATH.'/*')) {
                return OrderApiResponse::fromThrowable($request, $error);
            }

            return BlogAutomationErrors::render($error, $request);
        });
        $exceptions->report(function (Throwable $error) {
            // OPS-DEPLOY-01: artisan has no request; asking for one here used to hide the real error behind
            // "Class \"request\" does not exist".
            if (app()->bound('request') && request()->is('api/automation/v1*')) {
                Log::error('blog.automation_failed', ['exception' => $error::class, 'code' => (string) $error->getCode()]);

                return false;
            }
        });
    })->create();
