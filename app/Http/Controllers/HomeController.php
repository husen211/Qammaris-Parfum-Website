<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $bestSellers = Product::with(['brand', 'primaryImage'])
            ->published()
            ->bestSellers()
            ->take(6)
            ->get();

        $featuredPosts = BlogPost::published()
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('home', compact('bestSellers', 'featuredPosts'));
    }
}
