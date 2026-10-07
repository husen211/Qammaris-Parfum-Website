<?php

use App\Http\Controllers\Admin\AdminBlogPostController;
use App\Http\Controllers\Admin\AdminBlogTaxonomyController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AdminProductImportController;
use App\Http\Controllers\Admin\AdminProductMaintenanceController;
use App\Http\Controllers\Admin\AdminQammarisAppProductController;
use App\Http\Controllers\Admin\AdminShopeeContentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\FragranceQuizController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');
// Sitemap route moved to routes/sitemap.php (no web middleware)

// Products
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

// Cart
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::put('/update/{id}', [CartController::class, 'update'])->name('update');
    Route::delete('/remove/{id}', [CartController::class, 'remove'])->name('remove');
    Route::post('/clear', [CartController::class, 'clear'])->name('clear');
    Route::get('/checkout', [CartController::class, 'showCheckout'])->name('checkout.show');
    Route::post('/checkout', [CartController::class, 'checkout'])->name('checkout');
    // cart data for drawer
    Route::get('/data', [CartController::class, 'getCartData'])->name('data');
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

// Fragrance Quiz
Route::get('/fragrance-quiz', [FragranceQuizController::class, 'index'])->name('quiz.index');
Route::post('/fragrance-quiz', [FragranceQuizController::class, 'store'])->name('quiz.store');
Route::get('/fragrance-quiz/result', [FragranceQuizController::class, 'result'])->name('quiz.result');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    Route::get('app-products', [AdminQammarisAppProductController::class, 'index'])->name('app-products.index');
    Route::post('app-products/preview', [AdminQammarisAppProductController::class, 'preview'])->middleware('throttle:6,1')->name('app-products.preview');
    Route::post('app-products/sync', [AdminQammarisAppProductController::class, 'sync'])->middleware('throttle:6,1')->name('app-products.sync');
    Route::post('app-products/{productImportBatch}/apply', [AdminQammarisAppProductController::class, 'apply'])->name('app-products.apply');

    // Dashboard Admin Sederhana
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

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

    // CRUD Blog Posts
    Route::get('blog-taxonomy', [AdminBlogTaxonomyController::class, 'index'])->name('blog-taxonomy.index');
    Route::post('blog-taxonomy', [AdminBlogTaxonomyController::class, 'store'])->name('blog-taxonomy.store');
    Route::patch('blog-taxonomy/{kind}/{id}/status', [AdminBlogTaxonomyController::class, 'status'])->name('blog-taxonomy.status');
    Route::match(['post', 'put'], 'blog-posts/preview', [AdminBlogPostController::class, 'preview'])->name('blog-posts.preview');
    Route::match(['post', 'put'], 'blog-posts/{blogPost}/preview', [AdminBlogPostController::class, 'preview'])->name('blog-posts.preview-existing');
    Route::patch('blog-posts/{blogPost}/restore', [AdminBlogPostController::class, 'restore'])->name('blog-posts.restore');
    Route::resource('blog-posts', AdminBlogPostController::class)->except(['show']);
});

// === ROUTE AUTHENTICATION (MANUAL) ===
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
