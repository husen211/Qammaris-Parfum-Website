<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index()
    {
        $eligibleIds = Product::query()
            ->published()
            ->bestSellers()
            ->orderBy('id')
            ->pluck('id');

        $bestSellers = collect();
        if ($eligibleIds->isNotEmpty()) {
            // Rotate one place per Palu calendar day; requests never mutate merchandising data.
            $today = now('Asia/Makassar')->startOfDay();
            $dayIndex = (int) $today->copy()->setDate(2000, 1, 1)->diffInDays($today);
            $offset = $dayIndex % $eligibleIds->count();
            $selectedIds = $eligibleIds->slice($offset)
                ->concat($eligibleIds->take($offset))
                ->take(10)
                ->values();
            $positions = $selectedIds->flip();

            $bestSellers = Product::with(['brand', 'primaryImage', 'activeOffer'])
                ->published()
                ->bestSellers()
                ->whereIn('id', $selectedIds)
                ->get()
                ->sortBy(fn (Product $product) => $positions[$product->id])
                ->values();
        }

        $featuredPosts = BlogPost::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        // UX-02: every brand on sale; logos from public/images/brands/<slug>.webp (or the slugged name, for brands whose
        // stored slug differs, UI-BRANDS-01), otherwise the brand name.
        $distributorBrands = Brand::active()
            ->withCount(['products as published_count' => fn ($products) => $products->published()])
            ->get()
            ->where('published_count', '>', 0)
            ->map(function (Brand $brand) {
                $file = collect([$brand->slug, Str::slug($brand->name)])
                    ->map(fn ($slug) => "images/brands/{$slug}.webp")
                    ->first(fn ($candidate) => is_file(public_path($candidate)));
                if ($file) {
                    [$width, $height] = getimagesize(public_path($file));
                    $brand->setAttribute('logo_mark', ['src' => asset($file), 'width' => intdiv($width, 2), 'height' => intdiv($height, 2)]);
                }

                return $brand;
            })
            ->sortBy([fn (Brand $a, Brand $b) => isset($b->logo_mark) <=> isset($a->logo_mark), ['published_count', 'desc'], ['name', 'asc']])
            ->values();

        return view('home', compact('bestSellers', 'featuredPosts', 'distributorBrands'));
    }
}
