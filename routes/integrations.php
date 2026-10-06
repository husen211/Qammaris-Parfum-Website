<?php

use App\Http\Controllers\Integrations\QammarisAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/qammaris-app/webhook', QammarisAppWebhookController::class)
    ->middleware('throttle:120,1')->name('integrations.qammaris-app.webhook');
