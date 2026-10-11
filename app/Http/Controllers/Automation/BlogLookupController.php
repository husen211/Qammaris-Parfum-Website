<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\Product;
use Illuminate\Http\Request;

class BlogLookupController extends Controller
{
    public function taxonomy()
    {
        return response()->json(['data' => [
            'categories' => BlogCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => BlogTag::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ], 'meta' => (object) []]);
    }

    public function products(Request $request)
    {
        $input = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);
        $page = Product::published()->with(['brand', 'primaryImage', 'activeOffer'])->search($input['search'] ?? '')->orderBy('id')->paginate($input['per_page'] ?? 20);

        return response()->json(['data' => $page->getCollection()->map(fn ($product) => [
            'id' => $product->id, 'name' => $product->name, 'brand' => $product->brand?->name,
            'url' => route('products.show', $product), 'image_url' => $product->primaryImage?->image_url,
            'volume_ml' => $product->activeOffer?->volume, 'price' => $product->activeOffer?->price,
            'availability' => $product->effective_availability,
        ]), 'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }
}
