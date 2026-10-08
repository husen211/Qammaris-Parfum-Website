<?php

use App\Http\Controllers\Integrations\OrderApiReadController;
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
});
