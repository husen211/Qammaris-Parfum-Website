<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['brand', 'category', 'primaryImage'])
            ->withMin('variants', 'price')
            ->active();
            
        // Search
        if ($request->filled('search')) {
            $query->search($request->search);
        }
        
        // Filter by brand
        if ($request->filled('brand')) {
            $brands = (array) $request->brand;
            if (count($brands) > 1) {
                $query->whereIn('brand_id', $brands);
            } else {
                $query->byBrand($brands[0]);
            }
        }
        
        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        
        // Filter by gender
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }
        
        // Sort
        $sort = $request->get('sort', 'latest');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('base_price', 'desc');
                break;
            case 'popular':
                $query->orderBy('view_count', 'desc');
                break;
            default:
                $query->latest();
        }
        
        $products = $query->paginate(10);
        $brands = Brand::active()->get();
        $categories = Category::all();
        
        return view('products.index', compact('products', 'brands', 'categories'));
    }
    
    public function show(Product $product)
    {
        $product->load(['brand', 'category', 'images', 'variants.product']);
        $product->incrementViewCount();
        
        $relatedProducts = Product::with(['brand', 'primaryImage'])
            ->withMin('variants', 'price')
            ->active()
            ->where('brand_id', $product->brand_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();
            
        return view('products.show', compact('product', 'relatedProducts'));
    }
}
