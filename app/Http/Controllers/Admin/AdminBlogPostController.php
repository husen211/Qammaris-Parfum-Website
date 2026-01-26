<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostStoreRequest;
use App\Http\Requests\Admin\BlogPostUpdateRequest;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBlogPostController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'published') {
                $query->where('is_published', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            } elseif ($status === 'scheduled') {
                $query->where('is_published', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '>', now());
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            }
        }

        $posts = $query->latest()->paginate(10)->withQueryString();
        $categories = BlogPost::CATEGORY_OPTIONS;

        return view('admin.blog-posts.index', compact('posts', 'categories'));
    }

    public function create()
    {
        $categories = BlogPost::CATEGORY_OPTIONS;

        return view('admin.blog-posts.create', compact('categories'));
    }

    public function store(BlogPostStoreRequest $request)
    {
        $payload = $request->validated();

        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('blog', 'public');
            $payload['featured_image'] = 'storage/' . $path;
        }

        $payload['author'] = $payload['author'] ?: optional($request->user())->name ?: 'Admin';
        $payload['meta_description'] = $payload['meta_description'] ?: $payload['excerpt'];

        $payload['is_published'] = $request->boolean('is_published');
        $payload['published_at'] = $this->resolvePublishedAt(
            $payload['is_published'],
            $payload['published_at'] ?? null
        );

        BlogPost::create($payload);

        return redirect()->route('admin.blog-posts.index')->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $blogPost)
    {
        $categories = BlogPost::CATEGORY_OPTIONS;

        return view('admin.blog-posts.edit', compact('blogPost', 'categories'));
    }

    public function update(BlogPostUpdateRequest $request, BlogPost $blogPost)
    {
        $payload = $request->validated();

        if ($request->hasFile('featured_image')) {
            $this->deleteFeaturedImage($blogPost);
            $path = $request->file('featured_image')->store('blog', 'public');
            $payload['featured_image'] = 'storage/' . $path;
        }

        $payload['author'] = $payload['author'] ?: optional($request->user())->name ?: 'Admin';
        $payload['meta_description'] = $payload['meta_description'] ?: $payload['excerpt'];

        $payload['is_published'] = $request->boolean('is_published');
        $payload['published_at'] = $this->resolvePublishedAt(
            $payload['is_published'],
            $payload['published_at'] ?? null
        );

        $blogPost->update($payload);

        return redirect()->route('admin.blog-posts.index')->with('success', 'Blog post updated.');
    }

    public function destroy(BlogPost $blogPost)
    {
        $this->deleteFeaturedImage($blogPost);
        $blogPost->delete();

        return redirect()->route('admin.blog-posts.index')->with('success', 'Blog post deleted.');
    }

    private function resolvePublishedAt(bool $isPublished, ?string $publishedAt): ?Carbon
    {
        if (!$isPublished) {
            return null;
        }

        if ($publishedAt) {
            return Carbon::parse($publishedAt);
        }

        return now();
    }

    private function deleteFeaturedImage(BlogPost $blogPost): void
    {
        if (!$blogPost->featured_image) {
            return;
        }

        if (Str::startsWith($blogPost->featured_image, 'storage/')) {
            $path = Str::after($blogPost->featured_image, 'storage/');
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
