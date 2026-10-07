<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\RenderBlogContent;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::with('editorialCategory')->published()->latest('published_at');

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        $posts = $query->paginate(9)->withQueryString();
        $categories = $this->visibleCategories();

        if ($categories->isEmpty()) {
            $categories = collect(BlogPost::CATEGORY_OPTIONS);
        }

        return view('blog.index', compact('posts', 'categories'));
    }

    public function show(BlogPost $post, RenderBlogContent $renderer)
    {
        abort_unless($post->isPubliclyVisible(), 404);

        $post->incrementViewCount();
        $post->setAttribute('content', $renderer->handle($post->content));

        $relatedPosts = BlogPost::with('editorialCategory')->published()
            ->byCategory($post->category)
            ->where('id', '!=', $post->id)
            ->take(3)
            ->get();

        return view('blog.show', compact('post', 'relatedPosts'));
    }

    public function category($category)
    {
        $posts = BlogPost::with('editorialCategory')->published()
            ->byCategory($category)
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $categories = $this->visibleCategories();

        if ($categories->isEmpty()) {
            $categories = collect(BlogPost::CATEGORY_OPTIONS);
        }

        return view('blog.index', compact('posts', 'category', 'categories'));
    }

    private function visibleCategories()
    {
        return BlogPost::published()->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_posts.category_id')
            ->selectRaw('COALESCE(blog_categories.name, blog_posts.category) AS display_category')
            ->distinct()->orderBy('display_category')->pluck('display_category');
    }
}
