<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\SyncSingleOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AdminProductController extends Controller
{
    // 1. INDEX: List Produk + Search + Filter Brand
    public function index(Request $request)
    {
        $query = Product::with(['brand', 'category', 'primaryImage', 'variants']);

        // Logic Search
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Logic Filter Brand (BARU)
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        $products = $query->latest()->paginate(10);

        // Ambil data brand untuk dropdown filter
        $brands = Brand::orderBy('name', 'asc')->get();

        return view('admin.products.index', compact('products', 'brands'));
    }

    // ... (SISA FUNCTION CREATE, STORE, EDIT, UPDATE, DESTROY TETAP SAMA SEPERTI SEBELUMNYA)
    // Pastikan kamu menyalin function lainnya dari kode sebelumnya jika belum ada.

    // Copy function create() sampai destroyImage() dari percakapan sebelumnya ke sini.
    // Kode di bawah hanya referensi function yang diubah (index).

    public function create()
    {
        $brands = Brand::where('is_active', true)->get();
        $categories = Category::all();

        return view('admin.products.create', compact('brands', 'categories'));
    }

    public function store(ProductStoreRequest $request, SyncSingleOffer $syncSingleOffer)
    {
        // ... (Gunakan kode STORE dari jawaban sebelumnya)
        $storedImagePaths = [];

        try {
            DB::beginTransaction();
            $compareAtPrice = $request->filled('compare_at_price')
                ? $request->compare_at_price
                : null;

            $fragranceNotes = [
                'top' => array_map('trim', explode(',', $request->top_notes)),
                'middle' => array_map('trim', explode(',', $request->middle_notes)),
                'base' => array_map('trim', explode(',', $request->base_notes)),
            ];

            $product = Product::create([
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'base_price' => null,
                'compare_at_price' => $compareAtPrice,
                'fragrance_notes' => $fragranceNotes,
                'gender' => $request->gender,
                'is_best_seller' => $request->has('is_best_seller'),
                'is_active' => true,
                'publication_status' => Product::PUBLICATION_PUBLISHED,
                'published_at' => now(),
                'availability_status' => Product::AVAILABILITY_UNKNOWN,
            ]);

            $offerData = array_values($request->validated('variants'))[0];
            $syncSingleOffer->handle($product, $offerData);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $this->storeProductImage($image, $storedImagePaths);
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index == 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('admin.products.index')->with('success', 'Product created successfully!');

        } catch (Throwable $e) {
            DB::rollback();
            $this->cleanupStoredImages($storedImagePaths);
            report($e);

            return back()->with('error', 'Produk gagal disimpan. Silakan coba lagi.')->withInput();
        }
    }

    public function edit($id)
    {
        $product = Product::with(['variants', 'images', 'brand', 'category'])->findOrFail($id);
        $brands = Brand::where('is_active', true)->get();
        $categories = Category::all();

        return view('admin.products.edit', compact('product', 'brands', 'categories'));
    }

    public function update(ProductUpdateRequest $request, $id, SyncSingleOffer $syncSingleOffer)
    {
        // ... (Gunakan kode UPDATE dari jawaban sebelumnya)
        $product = Product::findOrFail($id);
        $storedImagePaths = [];

        try {
            DB::beginTransaction();
            $compareAtPrice = $request->filled('compare_at_price')
                ? $request->compare_at_price
                : null;

            $fragranceNotes = [
                'top' => array_map('trim', explode(',', $request->top_notes)),
                'middle' => array_map('trim', explode(',', $request->middle_notes)),
                'base' => array_map('trim', explode(',', $request->base_notes)),
            ];

            $product->update([
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'compare_at_price' => $compareAtPrice,
                'fragrance_notes' => $fragranceNotes,
                'gender' => $request->gender,
                'is_best_seller' => $request->has('is_best_seller'),
            ]);

            $offerData = array_values($request->validated('variants'))[0];
            $syncSingleOffer->handle($product, $offerData);

            if ($request->hasFile('new_images')) {
                $lastSort = $product->images()->max('sort_order') ?? 0;
                $hasPrimary = $product->images()->where('is_primary', true)->exists();

                foreach ($request->file('new_images') as $index => $image) {
                    $lastSort++;
                    $path = $this->storeProductImage($image, $storedImagePaths);
                    $setPrimary = ! $hasPrimary && $index === 0;

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $setPrimary,
                        'sort_order' => $lastSort,
                    ]);

                    if ($setPrimary) {
                        $hasPrimary = true;
                    }
                }
            }

            DB::commit();

            return redirect()->route('admin.products.index')->with('success', 'Product updated successfully!');

        } catch (Throwable $e) {
            DB::rollback();
            $this->cleanupStoredImages($storedImagePaths);
            report($e);

            return back()->with('error', 'Produk gagal diperbarui. Silakan coba lagi.')->withInput();
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->publication_status === Product::PUBLICATION_ARCHIVED && ! $product->is_active) {
            return back()->with('success', 'Produk ini sudah diarsipkan.');
        }

        $product->markArchived();

        return back()->with('success', 'Produk berhasil diarsipkan. Data dan gambar tetap tersimpan.');
    }

    public function restore($id)
    {
        $product = Product::findOrFail($id);

        if ($product->isPublished()) {
            return back()->with('success', 'Produk ini sudah aktif.');
        }

        $product->markPublished();

        return back()->with('success', 'Produk berhasil diaktifkan kembali.');
    }

    public function destroyImage($id)
    {
        ProductImage::findOrFail($id);

        return back()->with(
            'error',
            'Penghapusan gambar dinonaktifkan sementara agar file dan metadata tetap aman.'
        );
    }

    private function storeProductImage($image, array &$storedImagePaths): string
    {
        $path = $image->store('products', 'public');

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('Uploaded product image could not be verified on storage.');
        }

        $storedImagePaths[] = $path;

        return $path;
    }

    private function cleanupStoredImages(array $storedImagePaths): void
    {
        if ($storedImagePaths === []) {
            return;
        }

        try {
            if (! Storage::disk('public')->delete($storedImagePaths)) {
                report(new RuntimeException('One or more rolled-back product images could not be deleted.'));
            }
        } catch (Throwable $cleanupError) {
            report($cleanupError);
        }
    }
}
