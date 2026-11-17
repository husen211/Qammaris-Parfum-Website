<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\CatalogCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    private const NEW_ARRIVAL_DAYS = 60;

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

        $newArrivals = $this->newArrivals();

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
                // SEO-03: brands with a permanent page link to it from the row.
                $brand->setAttribute('page_url', CatalogCollection::brand($brand)?->url());

                return $brand;
            })
            ->sortBy([fn (Brand $a, Brand $b) => isset($b->logo_mark) <=> isset($a->logo_mark), ['published_count', 'desc'], ['name', 'asc']])
            ->values();

        return view('home', compact('newArrivals', 'bestSellers', 'featuredPosts', 'distributorBrands'));
    }

    /**
     * HOME-NEW-ARRIVAL (Owner 2026-11-17): the five newest first publications of the last 60 days that have a real
     * photo, a priced offer and are not sold out. Fewer than three hides the section.
     */
    private function newArrivals(): Collection
    {
        $arrivals = Product::with(['brand', 'primaryImage', 'activeOffer'])
            ->published()
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays(self::NEW_ARRIVAL_DAYS))
            ->where(fn ($availability) => $availability->whereNull('availability_status')
                ->orWhere('availability_status', '!=', Product::AVAILABILITY_SOLD_OUT))
            ->whereHas('primaryImage')
            ->whereHas('activeOffer', fn ($offer) => $offer->where('price', '>', 0))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            // The photo file check needs the storage disk, so a few extra rows cover products whose file is missing.
            ->take(15)
            ->get()
            ->filter(fn (Product $product) => $product->activeOffer?->price > 0
                && $product->effective_availability !== Product::AVAILABILITY_SOLD_OUT
                && ! str_ends_with($product->primaryImage->image_url, ProductImage::PLACEHOLDER_URL))
            ->take(5)
            ->values();

        return $arrivals->count() >= 3 ? $arrivals : collect();
    }
}
