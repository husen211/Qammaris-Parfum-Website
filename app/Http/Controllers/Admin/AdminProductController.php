<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    public function store(ProductStoreRequest $request)
    {
        // ... (Gunakan kode STORE dari jawaban sebelumnya)
        try {
            DB::beginTransaction();
            $skuPrefix = $this->resolveSkuPrefix($request->brand_id);
            $compareAtPrice = $request->filled('compare_at_price')
                ? $request->compare_at_price
                : null;

            $fragranceNotes = [
                'top' => array_map('trim', explode(',', $request->top_notes)),
                'middle' => array_map('trim', explode(',', $request->middle_notes)),
                'base' => array_map('trim', explode(',', $request->base_notes)),
            ];

            $basePrice = collect($request->variants)->min('price');

            $product = Product::create([
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'base_price' => $basePrice,
                'compare_at_price' => $compareAtPrice,
                'fragrance_notes' => $fragranceNotes,
                'gender' => $request->gender,
                'is_best_seller' => $request->has('is_best_seller'),
                'is_active' => true,
            ]);

            foreach ($request->variants as $variantData) {
                $this->createVariant($product, $variantData, $skuPrefix);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index == 0,
                        'sort_order' => $index
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('admin.products.index')->with('success', 'Product created successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    public function edit($id)
    {
        $product = Product::with(['variants', 'images', 'brand', 'category'])->findOrFail($id);
        $brands = Brand::where('is_active', true)->get();
        $categories = Category::all();

        return view('admin.products.edit', compact('product', 'brands', 'categories'));
    }

    public function update(ProductUpdateRequest $request, $id)
    {
        // ... (Gunakan kode UPDATE dari jawaban sebelumnya)
         $product = Product::findOrFail($id);

        try {
            DB::beginTransaction();
            $skuPrefix = $this->resolveSkuPrefix($request->brand_id);
            $compareAtPrice = $request->filled('compare_at_price')
                ? $request->compare_at_price
                : null;

            $fragranceNotes = [
                'top' => array_map('trim', explode(',', $request->top_notes)),
                'middle' => array_map('trim', explode(',', $request->middle_notes)),
                'base' => array_map('trim', explode(',', $request->base_notes)),
            ];

            $basePrice = collect($request->variants)->min('price');

            $product->update([
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'base_price' => $basePrice,
                'compare_at_price' => $compareAtPrice,
                'fragrance_notes' => $fragranceNotes,
                'gender' => $request->gender,
                'is_best_seller' => $request->has('is_best_seller'),
            ]);

            $submittedVariantIds = collect($request->variants)->pluck('id')->filter()->toArray();
            
            ProductVariant::where('product_id', $product->id)
                ->whereNotIn('id', $submittedVariantIds)
                ->delete();

            foreach ($request->variants as $variantData) {
                if (isset($variantData['id']) && $variantData['id']) {
                    ProductVariant::where('id', $variantData['id'])->update([
                        'volume' => $variantData['volume'],
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                    ]);
                } else {
                    $this->createVariant($product, $variantData, $skuPrefix);
                }
            }

            if ($request->hasFile('new_images')) {
                $lastSort = $product->images()->max('sort_order') ?? 0;
                $hasPrimary = $product->images()->where('is_primary', true)->exists();

                foreach ($request->file('new_images') as $index => $image) {
                    $lastSort++;
                    $path = $image->store('products', 'public');
                    $setPrimary = !$hasPrimary && $index === 0;

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $setPrimary,
                        'sort_order' => $lastSort
                    ]);

                    if ($setPrimary) {
                        $hasPrimary = true;
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.products.index')->with('success', 'Product updated successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
         // ... (Gunakan kode DESTROY dari jawaban sebelumnya)
        $product = Product::findOrFail($id);
        foreach($product->images as $image) {
            if(Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }
        }
        $product->delete();
        return back()->with('success', 'Product deleted successfully!');
    }

    public function destroyImage($id)
    {
         // ... (Gunakan kode DESTROY IMAGE dari jawaban sebelumnya)
        $image = ProductImage::findOrFail($id);
        
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }
        
        $productId = $image->product_id;
        $isPrimary = $image->is_primary;
        
        $image->delete();

        if ($isPrimary) {
            $nextImage = ProductImage::where('product_id', $productId)->first();
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        return back()->with('success', 'Image removed.');
    }

    private function createVariant($product, $data, string $skuPrefix)
    {
        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => $data['volume'],
            'price' => $data['price'],
            'stock' => $data['stock'],
            'sku' => $skuPrefix . '-' . rand(1000, 9999),
            'is_active' => true
        ]);
    }

    private function resolveSkuPrefix(int $brandId): string
    {
        $brandName = Brand::whereKey($brandId)->value('name');
        $fallback = 'PRD';

        if (!$brandName) {
            return $fallback;
        }

        return strtoupper(substr($brandName, 0, 3)) ?: $fallback;
    }
}
