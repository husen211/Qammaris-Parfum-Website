<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::published()->latest('published_at');
        
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        
        $posts = $query->paginate(9)->withQueryString();
        $categories = BlogPost::published()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        if ($categories->isEmpty()) {
            $categories = collect(BlogPost::CATEGORY_OPTIONS);
        }
        
        return view('blog.index', compact('posts', 'categories'));
    }
    
    public function show(BlogPost $post)
    {
        if (!$post->is_published) {
            abort(404);
        }
        
        $post->incrementViewCount();
        
        $relatedPosts = BlogPost::published()
            ->whereRaw('lower(category) = ?', [Str::lower($post->category)])
            ->where('id', '!=', $post->id)
            ->take(3)
            ->get();
            
        return view('blog.show', compact('post', 'relatedPosts'));
    }
    
    public function category($category)
    {
        $posts = BlogPost::published()
            ->byCategory($category)
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();
            
        $categories = BlogPost::published()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        if ($categories->isEmpty()) {
            $categories = collect(BlogPost::CATEGORY_OPTIONS);
        }
            
        return view('blog.index', compact('posts', 'category', 'categories'));
    }
}
