<?php

use App\Http\Controllers\PublicPwaController;
use Illuminate\Support\Facades\Route;

// PWA-01. Loaded without the web middleware group: these static resources never start a session or set cookies.
Route::get('manifest.webmanifest', [PublicPwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('sw.js', [PublicPwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('offline', [PublicPwaController::class, 'offline'])->name('pwa.offline');
