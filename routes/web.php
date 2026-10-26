<?php

use App\Http\Controllers\Admin\AdminAccountController;
use App\Http\Controllers\Admin\AdminBlogAutomationController;
use App\Http\Controllers\Admin\AdminBlogMediaController;
use App\Http\Controllers\Admin\AdminBlogPostController;
use App\Http\Controllers\Admin\AdminBlogTaxonomyController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFragrancePreferenceController;
use App\Http\Controllers\Admin\AdminOnlineOrderController;
use App\Http\Controllers\Admin\AdminOnlineOrderV2Controller;
use App\Http\Controllers\Admin\AdminOrderIntegrationController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AdminProductImportController;
use App\Http\Controllers\Admin\AdminProductMaintenanceController;
use App\Http\Controllers\Admin\AdminPushSubscriptionController;
use App\Http\Controllers\Admin\AdminQammarisAppProductController;
use App\Http\Controllers\Admin\AdminShopeeContentController;
use App\Http\Controllers\Admin\AdminStorePaymentAccountController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\FragrancePreferenceController;
use App\Http\Controllers\FragranceQuizController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OnlineOrderController;
use App\Http\Controllers\OnlineOrderStaffController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RestockRequestController;
use App\Http\Controllers\StoreController;
use App\Http\Middleware\FragranceBrowser;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');
// Sitemap route moved to routes/sitemap.php (no web middleware)

// Products
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
// SEO-03: permanent collection and brand pages (config/catalog_collections.php).
Route::get('/koleksi/{collection}', [ProductController::class, 'collection'])->name('products.collection');
Route::get('/brand/{brand:slug}', [ProductController::class, 'brand'])->name('products.brand');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show')
    // SEO-04: old catalog URLs Google still requests move permanently to the same product; other slugs stay 404.
    ->missing(function (Request $request) {
        $target = config('product_redirects')[$request->route('product')] ?? null;
        abort_unless($target && Product::published()->where('slug', $target)->exists(), 404);

        return redirect()->route('products.show', $target, 301);
    });
// WAIT-01: sold-out product → Qammaris App Daftar Tunggu.
Route::post('/products/{product:slug}/kabari', [RestockRequestController::class, 'store'])
    ->middleware('throttle:restock-request')->name('products.restock-request');

// Cart
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::put('/update/{id}', [CartController::class, 'update'])->name('update');
    Route::delete('/remove/{id}', [CartController::class, 'remove'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
    Route::get('/checkout', [CartController::class, 'showCheckout'])->name('checkout.show');
    Route::post('/checkout', [CartController::class, 'checkout'])->middleware('throttle:website-checkout')->name('checkout');
    // cart data for drawer
    Route::get('/data', [CartController::class, 'getCartData'])->name('data');
});

// Online orders: private bearer links; the token is the only lookup key and is never listed.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/pesanan-saya', [OnlineOrderController::class, 'mine'])->name('orders.customer.mine');
    Route::get('/pesanan/{token}', [OnlineOrderController::class, 'show'])->name('orders.customer.show');
    Route::get('/tugas-pesanan/{token}', [OnlineOrderStaffController::class, 'show'])->name('orders.staff.show');
});
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/pesanan-saya/lupakan', [OnlineOrderController::class, 'forgetMine'])->name('orders.customer.forget');
    Route::post('/pesanan/{token}', [OnlineOrderController::class, 'submit'])->name('orders.customer.submit');
    Route::post('/pesanan/{token}/diterima', [OnlineOrderController::class, 'confirmReceived'])->name('orders.customer.received');
    Route::post('/tugas-pesanan/{token}/langkah', [OnlineOrderStaffController::class, 'advance'])->name('orders.staff.advance');
    Route::post('/tugas-pesanan/{token}/talangan', [OnlineOrderStaffController::class, 'recordAdvance'])->name('orders.staff.advance-cost');
});

// Blog
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/{post:slug}', [BlogController::class, 'show'])->name('show');
    Route::get('/category/{category}', [BlogController::class, 'category'])->name('category');
});

// Store Info
Route::prefix('store')->name('store.')->group(function () {
    Route::get('/location', [StoreController::class, 'location'])->name('location');
    Route::get('/about', [StoreController::class, 'about'])->name('about');
});

// Anonymous preference access stays separate from account authentication.
Route::middleware([FragranceBrowser::class])->group(function () {
    Route::get('/fragrance-quiz', [FragranceQuizController::class, 'index'])->name('quiz.index');
    Route::post('/fragrance-quiz', [FragranceQuizController::class, 'store'])->middleware('throttle:fragrance-submit')->name('quiz.store');
    Route::get('/fragrance-quiz/result', [FragranceQuizController::class, 'result'])->name('quiz.result');
    Route::get('/fragrance-quiz/results/{id}', [FragrancePreferenceController::class, 'show'])->whereUuid('id')->name('quiz.v1.show');
    Route::post('/fragrance-quiz/results/{id}/recalculate', [FragrancePreferenceController::class, 'recalculate'])->whereUuid('id')->middleware('throttle:fragrance-submit')->name('quiz.v1.recalculate');
    Route::post('/fragrance-quiz/results/{id}/feedback', [FragrancePreferenceController::class, 'feedback'])->whereUuid('id')->middleware('throttle:fragrance-feedback')->name('quiz.v1.feedback');
});

// Admin login/logout stay inside the installed PWA scope /admin (ORD-02b). Admin pages are never stored by browsers.
Route::middleware(['guest', 'cache.headers:no_store;private'])->group(function () {
    Route::get('admin/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('admin/login', [AuthController::class, 'login'])->name('admin.login.perform');
});
Route::post('admin/logout', [AuthController::class, 'logout'])->name('admin.logout')->middleware('auth');

// Every admin route needs an active admin account (admin middleware) plus one area ability (ORD-02a).
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'cache.headers:no_store;private'])->group(function () {

    // Staff Order accounts are redirected from the dashboard to their order list.
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Own account (all admin roles)
    Route::get('account', [AdminAccountController::class, 'show'])->name('account');
    Route::get('account/password', [AdminAccountController::class, 'edit'])->name('account.password');
    Route::put('account/password', [AdminAccountController::class, 'update'])->name('account.password.update');

    // Catalog, taxonomy, imports and app products
    Route::middleware('can:catalog.manage')->group(function () {
        Route::get('app-products', [AdminQammarisAppProductController::class, 'index'])->name('app-products.index');
        Route::post('app-products/preview', [AdminQammarisAppProductController::class, 'preview'])->middleware('throttle:6,1')->name('app-products.preview');
        Route::post('app-products/sync', [AdminQammarisAppProductController::class, 'sync'])->middleware('throttle:6,1')->name('app-products.sync');
        Route::post('app-products/{productImportBatch}/apply', [AdminQammarisAppProductController::class, 'apply'])->name('app-products.apply');

        // Fragrance preference catalog profiles (merged from main): catalog data, same ability as products.
        Route::get('fragrance-preference', [AdminFragrancePreferenceController::class, 'index'])->name('fragrance.index');
        Route::post('fragrance-preference/preview', [AdminFragrancePreferenceController::class, 'preview'])->middleware('throttle:6,1')->name('fragrance.preview');
        Route::post('fragrance-preference/apply', [AdminFragrancePreferenceController::class, 'apply'])->middleware('throttle:6,1')->name('fragrance.apply');
        Route::get('fragrance-preference/profiles/{product:id}', [AdminFragrancePreferenceController::class, 'profile'])->name('fragrance.profile');
        Route::post('fragrance-preference/profiles/{product:id}', [AdminFragrancePreferenceController::class, 'review'])->middleware('throttle:10,1')->name('fragrance.review');

        // === TAMBAHAN BARU (Untuk fitur hapus gambar saat Edit) ===
        Route::delete('products/image/{productImage}', [AdminProductController::class, 'destroyImage'])->name('products.delete-image');

        // Archive is reversible; product hard deletion is intentionally unavailable in admin.
        Route::patch('products/{product}/restore', [AdminProductController::class, 'restore'])->name('products.restore');
        Route::patch('products/{product}/images/{productImage}/primary', [AdminProductImageController::class, 'primary'])->name('products.images.primary');
        Route::patch('products/{product}/images/{productImage}/move', [AdminProductImageController::class, 'move'])->name('products.images.move');
        Route::delete('products/{product}/images/{productImage}', [AdminProductImageController::class, 'destroy'])->name('products.images.destroy');

        Route::get('product-imports', [AdminProductImportController::class, 'create'])->name('product-imports.create');
        Route::get('shopee-imports', [AdminShopeeContentController::class, 'index'])->name('shopee-imports.index');
        Route::post('shopee-imports/preview', [AdminShopeeContentController::class, 'preview'])->middleware('throttle:6,1')->name('shopee-imports.preview');
        Route::post('shopee-imports/{productImportBatch}/rows/{productImportRow}/choose', [AdminShopeeContentController::class, 'choose'])->name('shopee-imports.choose');
        Route::post('shopee-imports/{productImportBatch}/apply', [AdminShopeeContentController::class, 'apply'])->middleware('throttle:6,1')->name('shopee-imports.apply');
        Route::post('shopee-imports/{productImportBatch}/refresh', [AdminShopeeContentController::class, 'refresh'])->middleware('throttle:6,1')->name('shopee-imports.refresh');
        Route::post('shopee-imports/{productImportBatch}/images', [AdminShopeeContentController::class, 'images'])->middleware('throttle:6,1')->name('shopee-imports.images');
        Route::post('shopee-imports/{productImportBatch}/publish', [AdminShopeeContentController::class, 'publish'])->middleware('throttle:6,1')->name('shopee-imports.publish');
        Route::get('product-imports/template', [AdminProductImportController::class, 'template'])->name('product-imports.template');
        Route::get('product-imports/catalog-snapshot.csv', [AdminProductImportController::class, 'catalogSnapshot'])->name('product-imports.catalog-snapshot');
        Route::get('product-imports/{productImportBatch}/report.csv', [AdminProductImportController::class, 'report'])->name('product-imports.report');
        Route::post('product-imports/preview', [AdminProductImportController::class, 'preview'])->name('product-imports.preview');
        Route::post('product-imports/{productImportBatch}/apply', [AdminProductImportController::class, 'apply'])->name('product-imports.apply');
        Route::post('product-imports/{productImportBatch}/images', [AdminProductImportController::class, 'acquireImages'])->name('product-imports.images');
        Route::post('product-imports/{productImportBatch}/rows/{productImportRow}/resolve-protected', [AdminProductImportController::class, 'resolveProtected'])->name('product-imports.resolve-protected');

        Route::get('product-maintenance', [AdminProductMaintenanceController::class, 'create'])->name('product-maintenance.create');
        Route::get('product-maintenance/template', [AdminProductMaintenanceController::class, 'template'])->name('product-maintenance.template');
        Route::post('product-maintenance/preview', [AdminProductMaintenanceController::class, 'preview'])->name('product-maintenance.preview');
        Route::post('product-maintenance/{productImportBatch}/apply', [AdminProductMaintenanceController::class, 'apply'])->name('product-maintenance.apply');

        // CRUD Produk (Bawaan)
        Route::resource('products', AdminProductController::class);

        // Taxonomy records are never hard-deleted; status changes are reversible.
        Route::patch('brands/{brand}/status', [AdminBrandController::class, 'updateStatus'])->name('brands.status');
        Route::resource('brands', AdminBrandController::class)->except(['show', 'destroy']);
        Route::patch('categories/{category}/status', [AdminCategoryController::class, 'updateStatus'])->name('categories.status');
        Route::resource('categories', AdminCategoryController::class)->except(['show', 'destroy']);
    });

    // Online orders (Super Admin, Staff Order, legacy admin); money changes need orders.finance
    Route::middleware('can:orders.manage')->group(function () {
        // ORD-09: this browser receives a push when someone orders on the website.
        Route::post('orders/push-subscriptions', [AdminPushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('orders.push-subscriptions.store');
        Route::delete('orders/push-subscriptions', [AdminPushSubscriptionController::class, 'destroy'])->middleware('throttle:20,1')->name('orders.push-subscriptions.destroy');
        Route::post('orders/push-subscriptions/test', [AdminPushSubscriptionController::class, 'test'])->middleware('throttle:5,1')->name('orders.push-subscriptions.test');
        Route::prefix('orders/payment-accounts')->name('orders.payment-accounts.')->controller(AdminStorePaymentAccountController::class)
            ->middleware('can:orders.payment-accounts')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('{account}/verify', 'verify')->name('verify');
                Route::post('{account}/deactivate', 'deactivate')->name('deactivate');
            });
        // ORD-04: UAT-only opt-in of this browser to the official store WhatsApp number (Super Admin).
        Route::post('orders/uat-whatsapp', [AdminStorePaymentAccountController::class, 'uatWhatsapp'])->middleware('can:orders.payment-accounts')->name('orders.uat-whatsapp');
        Route::get('orders/product-search', [AdminOnlineOrderController::class, 'productSearch'])->name('orders.product-search');
        Route::get('orders/customer-search', [AdminOnlineOrderV2Controller::class, 'customerSearch'])->name('orders.customer-search');
        // ORD-05: saved customers (automatic), their orders and addresses; removal is Super Admin only.
        Route::prefix('customers')->name('customers.')->controller(AdminCustomerController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{customer}', 'show')->whereNumber('customer')->name('show');
            Route::patch('{customer}', 'update')->whereNumber('customer')->name('update');
            Route::post('{customer}/addresses/{address}/archive', 'archiveAddress')->whereNumber(['customer', 'address'])->name('addresses.archive');
            Route::post('{customer}/anonymize', 'anonymize')->whereNumber('customer')->middleware('can:customers.delete')->name('anonymize');
        });

        // V2 orders (ORD-02d): every action is a V2 domain operation; each form posts its revision.
        Route::prefix('orders/{order}/v2')->name('orders.v2.')->controller(AdminOnlineOrderV2Controller::class)->group(function () {
            Route::patch('details', 'updateDetails')->name('details');
            Route::post('confirm-website', 'confirmWebsite')->name('confirm-website');
            Route::post('customer-link', 'regenerateCustomerLink')->name('customer-link');
            Route::post('payments', 'recordPayment')->name('payments');
            Route::post('preparation', 'startPreparation')->name('preparation');
            Route::post('pack', 'pack')->name('pack');
            Route::post('courier-responsibility', 'courierResponsibility')->name('courier-responsibility');
            Route::post('courier', 'courier')->name('courier');
            Route::post('jnt', 'jnt')->name('jnt');
            Route::post('courier-cost', 'updateCourierCost')->name('courier-cost');
            // ORD-03: optional J&T QR (private file) and the customer-charged shipping fee.
            Route::post('jnt/qr', 'jntQr')->name('jnt.qr');
            Route::get('jnt/qr', 'showJntQr')->name('jnt.qr.show');
            Route::post('shipping', 'shipping')->name('shipping');
            Route::post('handover', 'handover')->name('handover');
            Route::post('delivery', 'delivery')->name('delivery');
            Route::post('issues', 'openIssue')->name('issues');
            Route::post('issues/{issue}/resolve', 'resolveIssue')->name('issues.resolve');
            Route::post('claims/{task}/release', 'releaseClaim')->whereIn('task', ['preparation', 'courier_booking', 'handover'])
                ->middleware('can:orders.refund')->name('claims.release');
            Route::post('keep/{action}', 'keep')->whereIn('action', ['start', 'stock', 'extend', 'release'])->name('keep');
            Route::post('cancel', 'cancel')->name('cancel');
            Route::post('customer', 'linkCustomer')->name('customer');
            Route::post('customer/address', 'saveAddress')->name('customer.address');
            Route::post('customer/address/use', 'useAddress')->name('customer.address.use');
            Route::post('adjustments', 'requestAdjustment')->name('adjustments');
            Route::post('adjustments/{adjustment}/{decision}', 'decideAdjustment')->whereIn('decision', ['approve', 'reject'])
                ->middleware('can:orders.approve-adjustment')->name('adjustments.decide');
            Route::post('change-requests/{changeRequest}/{decision}', 'decideChange')->whereIn('decision', ['approve', 'reject'])->name('change-requests.decide');
        });
        // Super Admin money panel; also reconciles ORD-01 orders, so it is not V2-only.
        Route::prefix('orders/{order}/money')->name('orders.money.')->controller(AdminOnlineOrderV2Controller::class)->middleware('can:orders.refund')->group(function () {
            Route::post('refund-decision', 'decideRefund')->name('refund-decision');
            Route::post('refunds', 'recordRefund')->name('refunds');
            Route::post('ledger/{entry}/reverse', 'reverseEntry')->name('reverse');
            Route::post('reconcile', 'reconcile')->name('reconcile');
        });
        Route::patch('orders/{order}/advance', [AdminOnlineOrderController::class, 'advance'])->name('orders.advance');
        Route::patch('orders/{order}/revert', [AdminOnlineOrderController::class, 'revert'])->middleware('can:orders.finance')->name('orders.revert');
        Route::patch('orders/{order}/cancel', [AdminOnlineOrderController::class, 'cancel'])->name('orders.cancel');
        Route::patch('orders/{order}/reimburse', [AdminOnlineOrderController::class, 'reimburse'])->middleware('can:orders.finance')->name('orders.reimburse');
        Route::post('orders/{order}/links', [AdminOnlineOrderController::class, 'regenerateLink'])->name('orders.regenerate-link');
        Route::resource('orders', AdminOnlineOrderController::class)->only(['index', 'create', 'store', 'show', 'update']);
    });

    // Blog
    Route::middleware('can:blog.manage')->group(function () {
        // Journal editor, media and taxonomy (merged from main): same ability as blog posts.
        Route::get('blog-posts/{blogPost}/automation', [AdminBlogAutomationController::class, 'edit'])->name('blog-automation.edit');
        Route::put('blog-posts/{blogPost}/automation', [AdminBlogAutomationController::class, 'update'])->name('blog-automation.update');
        Route::get('blog-posts/{blogPost}/media', [AdminBlogMediaController::class, 'index'])->name('blog-media.index');
        Route::post('blog-posts/{blogPost}/media', [AdminBlogMediaController::class, 'store'])->middleware('throttle:10,1')->name('blog-media.store');
        Route::patch('blog-posts/{blogPost}/media/{media}', [AdminBlogMediaController::class, 'update'])->middleware('throttle:10,1')->name('blog-media.update');
        Route::patch('blog-posts/{blogPost}/media/{media}/archive', [AdminBlogMediaController::class, 'archive'])->name('blog-media.archive');
        Route::get('blog-taxonomy', [AdminBlogTaxonomyController::class, 'index'])->name('blog-taxonomy.index');
        Route::post('blog-taxonomy', [AdminBlogTaxonomyController::class, 'store'])->name('blog-taxonomy.store');
        Route::patch('blog-taxonomy/{kind}/{id}/status', [AdminBlogTaxonomyController::class, 'status'])->name('blog-taxonomy.status');
        Route::match(['post', 'put'], 'blog-posts/preview', [AdminBlogPostController::class, 'preview'])->name('blog-posts.preview');
        Route::match(['post', 'put'], 'blog-posts/{blogPost}/preview', [AdminBlogPostController::class, 'preview'])->name('blog-posts.preview-existing');
        Route::patch('blog-posts/{blogPost}/restore', [AdminBlogPostController::class, 'restore'])->name('blog-posts.restore');
        Route::resource('blog-posts', AdminBlogPostController::class)->except(['show']);
    });

    // Qammaris App order integration status (Super Admin only, ORD-02e)
    Route::middleware('can:integrations.manage')->group(function () {
        Route::get('integrations/orders', [AdminOrderIntegrationController::class, 'index'])->name('integrations.orders');
        Route::post('integrations/orders/outbox/{outbox}/resend', [AdminOrderIntegrationController::class, 'resend'])->name('integrations.orders.resend');
    });

    // Pengguna & Role (Super Admin only)
    Route::middleware('can:users.manage')->group(function () {
        Route::patch('users/{user}/status', [AdminUserController::class, 'status'])->name('users.status');
        Route::post('users/{user}/password-reset', [AdminUserController::class, 'resetPassword'])->name('users.password-reset');
        Route::resource('users', AdminUserController::class)->except(['destroy']);
    });
});

// === ROUTE AUTHENTICATION (MANUAL) ===
Route::middleware('guest')->group(function () {
    // Kept for old bookmarks; the form lives at /admin/login. POST stays for older clients.
    Route::redirect('/login', '/admin/login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
