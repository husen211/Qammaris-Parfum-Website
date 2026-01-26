<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $bestSellers = Product::with(['brand', 'primaryImage'])
            ->active()
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
