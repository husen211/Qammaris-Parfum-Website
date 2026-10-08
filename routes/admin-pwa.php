<?php

use App\Http\Controllers\Admin\AdminPwaController;
use Illuminate\Support\Facades\Route;

// Loaded without the web middleware group: these static resources never start a session or set cookies.
Route::get('admin/manifest.webmanifest', [AdminPwaController::class, 'manifest'])->name('admin.pwa.manifest');
Route::get('admin/sw.js', [AdminPwaController::class, 'serviceWorker'])->name('admin.pwa.service-worker');
Route::get('admin/offline', [AdminPwaController::class, 'offline'])->name('admin.pwa.offline');
