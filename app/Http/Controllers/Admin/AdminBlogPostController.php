<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\ChangeBlogPostArchive;
use App\Actions\Blog\SaveBlogPost;
use App\Exceptions\BlogPostConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRevisionRequest;
use App\Http\Requests\Admin\BlogPostStoreRequest;
use App\Http\Requests\Admin\BlogPostUpdateRequest;
use App\Models\BlogPost;
use App\Support\SearchMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminBlogPostController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::query();
        if ($request->query('status') === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        $search = SearchMatcher::term($request->query('search'));

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

        if ($search !== '') {
            SearchMatcher::constrain($query, $search, (clone $query)->select(['id', 'title', 'excerpt'])->get(),
                fn ($row) => [$row->title, $row->excerpt]);
        }
        $posts = $query->latest()->paginate(10)->withQueryString();
        $categories = BlogPost::CATEGORY_OPTIONS;

        return view('admin.blog-posts.index', compact('posts', 'categories', 'search'));
    }

    public function create()
    {
        $categories = BlogPost::CATEGORY_OPTIONS;

        return view('admin.blog-posts.create', compact('categories'));
    }

    public function store(BlogPostStoreRequest $request, SaveBlogPost $save)
    {
        try {
            $save->handle($request->validated(), $request->user(), image: $request->file('featured_image'));
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            return $this->failed($request, $error);
        }

        return redirect()->route('admin.blog-posts.index')->with('success', 'Artikel disimpan.');
    }

    public function edit(BlogPost $blogPost)
    {
        if ($blogPost->archived_at !== null) {
            return redirect()->route('admin.blog-posts.index', ['status' => 'archived'])
                ->with('error', 'Pulihkan artikel sebagai draft sebelum mengedit.');
        }
        $categories = BlogPost::CATEGORY_OPTIONS;

        return view('admin.blog-posts.edit', compact('blogPost', 'categories'));
    }

    public function update(
        BlogPostUpdateRequest $request,
        BlogPost $blogPost,
        SaveBlogPost $save
    ) {
        try {
            $save->handle($request->validated(), $request->user(), $blogPost, $request->file('featured_image'));
        } catch (BlogPostConflict $error) {
            return $this->conflict($request, $error);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            return $this->failed($request, $error);
        }

        return redirect()->route('admin.blog-posts.index')->with('success', 'Artikel disimpan.');
    }

    public function destroy(BlogPostRevisionRequest $request, BlogPost $blogPost, ChangeBlogPostArchive $archive)
    {
        return $this->changeArchive($request, $blogPost, $archive, true);
    }

    public function restore(BlogPostRevisionRequest $request, BlogPost $blogPost, ChangeBlogPostArchive $archive)
    {
        return $this->changeArchive($request, $blogPost, $archive, false);
    }

    private function changeArchive(BlogPostRevisionRequest $request, BlogPost $post, ChangeBlogPostArchive $archive, bool $archiveIt)
    {
        try {
            $archive->handle($post, (int) $request->validated('revision'), $archiveIt, $request->user());
        } catch (BlogPostConflict $error) {
            return $this->conflict($request, $error);
        } catch (Throwable $error) {
            return $this->failed($request, $error);
        }

        return redirect()->route('admin.blog-posts.index', $archiveIt ? ['status' => 'archived'] : [])
            ->with('success', $archiveIt ? 'Artikel diarsipkan. Isi dan gambar tetap disimpan.' : 'Artikel dipulihkan sebagai draft.');
    }

    private function conflict(Request $request, BlogPostConflict $error)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => 'revision_conflict', 'message' => $error->getMessage()]], 409);
        }

        return back()->withInput()->withErrors(['revision' => $error->getMessage()])->with('error', $error->getMessage());
    }

    private function failed(Request $request, Throwable $error)
    {
        // Database exceptions may contain content bindings; never report a whole request/query.
        Log::error('blog.write_failed', ['exception' => $error::class]);
        if ($request->expectsJson()) {
            return response()->json(['error' => ['code' => 'save_failed', 'message' => 'Artikel belum tersimpan. Coba lagi.']], 500);
        }

        return back()->withInput()->with('error', 'Artikel belum tersimpan. Isi dan gambar sebelumnya tetap aman. Coba lagi; pilih ulang file gambar bila ada.');
    }
}
