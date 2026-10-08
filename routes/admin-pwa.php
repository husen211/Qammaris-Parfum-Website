<?php

use App\Http\Controllers\Admin\AdminPwaController;
use App\Http\Controllers\Admin\OrderTaskLinkController;
use Illuminate\Support\Facades\Route;

// Loaded without the web middleware group: these static resources never start a session or set cookies.
Route::get('admin/manifest.webmanifest', [AdminPwaController::class, 'manifest'])->name('admin.pwa.manifest');
Route::get('admin/sw.js', [AdminPwaController::class, 'serviceWorker'])->name('admin.pwa.service-worker');
Route::get('admin/offline', [AdminPwaController::class, 'offline'])->name('admin.pwa.offline');
// WhatsApp task link: Admin PWA or App order page, decided per click by QAMMARIS_ORDER_APP_TASK_LINKS (ORD-02e).
Route::get('admin/orders/task/{publicId}', OrderTaskLinkController::class)->name('admin.orders.task');
