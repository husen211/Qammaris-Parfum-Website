<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\JournalSearch;
use App\Support\RenderBlogContent;
use App\Support\SearchMatcher;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request, JournalSearch $searcher)
    {
        return $this->listing($request, $searcher, SearchMatcher::term($request->query('category')));
    }

    public function show(BlogPost $post, RenderBlogContent $renderer)
    {
        abort_unless($post->isPubliclyVisible(), 404);

        $post->incrementViewCount();
        $document = $renderer->document($post->content);
        $post->setAttribute('content', $document['html']);

        $relatedPosts = BlogPost::with('editorialCategory')->published()
            ->byCategory($post->category)
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')->orderByDesc('id')->take(3)
            ->get();

        $nextPost = BlogPost::with('editorialCategory')->published()
            ->whereNotIn('id', $relatedPosts->pluck('id')->push($post->id))
            ->orderByDesc('published_at')->orderByDesc('id')->first();

        return view('blog.show', compact('post', 'relatedPosts', 'nextPost') + $document);
    }

    public function category(Request $request, JournalSearch $searcher, string $category)
    {
        return $this->listing($request, $searcher, $category);
    }

    private function listing(Request $request, JournalSearch $searcher, string $category)
    {
        $search = SearchMatcher::term($request->query('search'));
        $query = BlogPost::with('editorialCategory')->published();
        if ($category !== '') {
            $query->byCategory($category);
        }
        if ($search !== '') {
            $searcher->apply($query, $search);
        }
        $featured = $category === '' && $search === '' && (int) $request->query('page', 1) === 1
            ? (clone $query)->where('is_featured', true)->orderByDesc('published_at')->orderByDesc('id')->first() : null;
        // Featured presentation does not remove the article from later paginated results.
        $posts = $query->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString();
        $categories = $this->visibleCategories();

        return view('blog.index', compact('posts', 'category', 'categories', 'search', 'featured'));
    }

    private function visibleCategories()
    {
        return BlogPost::published()->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_posts.category_id')
            ->selectRaw('COALESCE(blog_categories.name, blog_posts.category) AS display_category')
            ->distinct()->orderBy('display_category')->pluck('display_category');
    }
}
