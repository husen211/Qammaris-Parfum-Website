<?php

use App\Http\Controllers\Admin\AdminBlogPostController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductImageController;
use App\Http\Controllers\Admin\AdminProductImportController;
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
    Route::get('product-imports/template', [AdminProductImportController::class, 'template'])->name('product-imports.template');
    Route::post('product-imports/preview', [AdminProductImportController::class, 'preview'])->name('product-imports.preview');
    Route::post('product-imports/{productImportBatch}/apply', [AdminProductImportController::class, 'apply'])->name('product-imports.apply');
    Route::post('product-imports/{productImportBatch}/images', [AdminProductImportController::class, 'acquireImages'])->name('product-imports.images');
    Route::post('product-imports/{productImportBatch}/rows/{productImportRow}/resolve-protected', [AdminProductImportController::class, 'resolveProtected'])->name('product-imports.resolve-protected');

    // CRUD Produk (Bawaan)
    Route::resource('products', AdminProductController::class);

    // Taxonomy records are never hard-deleted; status changes are reversible.
    Route::patch('brands/{brand}/status', [AdminBrandController::class, 'updateStatus'])->name('brands.status');
    Route::resource('brands', AdminBrandController::class)->except(['show', 'destroy']);
    Route::patch('categories/{category}/status', [AdminCategoryController::class, 'updateStatus'])->name('categories.status');
    Route::resource('categories', AdminCategoryController::class)->except(['show', 'destroy']);

    // CRUD Blog Posts
    Route::resource('blog-posts', AdminBlogPostController::class)->except(['show']);
});

// === ROUTE AUTHENTICATION (MANUAL) ===
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
