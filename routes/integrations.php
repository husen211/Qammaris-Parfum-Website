<?php

use App\Http\Controllers\Integrations\OrderApiReadController;
use App\Http\Controllers\Integrations\OrderApiWriteController;
use App\Http\Controllers\Integrations\QammarisAppWebhookController;
use App\Http\Middleware\AuthenticateOrderApi;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/qammaris-app/webhook', QammarisAppWebhookController::class)
    ->middleware('throttle:120,1')->name('integrations.qammaris-app.webhook');

// Private Order API v1 (ORD-02e, contract r4.1 baseline). No sessions or cookies; every request is HMAC-signed.
Route::prefix('integrations/qammaris-app/orders/v1')->name('integrations.orders.')->middleware(AuthenticateOrderApi::class)->group(function () {
    Route::get('orders', [OrderApiReadController::class, 'index'])->name('index');
    Route::get('orders/{id}', [OrderApiReadController::class, 'show'])->name('show');
    Route::get('orders/{id}/jnt/qr', [OrderApiReadController::class, 'qr'])->name('jnt.qr.show');

    Route::controller(OrderApiWriteController::class)->group(function () {
        Route::post('orders/{id}/claims', 'claim')->name('claims.store');
        Route::delete('orders/{id}/claims/{task}', 'release')->name('claims.release');
        Route::post('orders/{id}/preparation', 'preparation')->name('preparation');
        Route::post('orders/{id}/courier-requests', 'courier')->name('courier');
        Route::post('orders/{id}/jnt', 'jnt')->name('jnt');
        Route::put('orders/{id}/jnt/qr', 'qr')->name('jnt.qr.store');
        Route::post('orders/{id}/handover', 'handover')->name('handover');
        Route::post('orders/{id}/delivery', 'delivery')->name('delivery');
        Route::post('orders/{id}/issues', 'openIssue')->name('issues.store');
        Route::post('orders/{id}/issues/{issueId}/resolve', 'resolveIssue')->name('issues.resolve');
        Route::put('orders/{id}/costs/{expenseRef}', 'cost')->name('costs');
    });
});
