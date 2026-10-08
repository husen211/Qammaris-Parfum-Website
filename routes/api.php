<?php

use App\Http\Controllers\Automation\BlogDraftController;
use App\Http\Controllers\Automation\BlogLookupController;
use Illuminate\Support\Facades\Route;

Route::prefix('automation/v1')->middleware(['blog.automation.enabled', 'auth:sanctum', 'throttle:blog-automation'])->group(function () {
    Route::get('blog-posts', [BlogDraftController::class, 'index'])->middleware('blog.automation.access:blog:read');
    Route::get('blog-posts/{id}', [BlogDraftController::class, 'show'])->whereNumber('id')->middleware('blog.automation.access:blog:read');
    Route::post('blog-posts', [BlogDraftController::class, 'store'])->middleware('blog.automation.access:blog:write');
    Route::patch('blog-posts/{id}', [BlogDraftController::class, 'update'])->whereNumber('id')->middleware('blog.automation.access:blog:write');
    Route::post('blog-posts/{id}/media', [BlogDraftController::class, 'media'])->whereNumber('id')->middleware(['blog.automation.access:blog:media', 'throttle:blog-automation-media']);
    Route::get('blog-taxonomy', [BlogLookupController::class, 'taxonomy'])->middleware('blog.automation.access:blog:read');
    Route::get('products', [BlogLookupController::class, 'products'])->middleware('blog.automation.access:catalog:read');
});
