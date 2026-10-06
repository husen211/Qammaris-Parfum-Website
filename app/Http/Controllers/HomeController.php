<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;

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
                ->take(6)
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

        return view('home', compact('bestSellers', 'featuredPosts'));
    }
}
