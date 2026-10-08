<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\SaveBlogMedia;
use App\Exceptions\BlogPostConflict;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\BlogImageProcessor;
use App\Services\BlogMediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminBlogMediaController extends Controller
{
    public function index(BlogPost $blogPost, BlogImageProcessor $processor)
    {
        abort_if($blogPost->archived_at !== null, 404);

        return view('admin.blog-posts.media', ['blogPost' => $blogPost, 'media' => $blogPost->media()->whereNull('archived_at')->orderByDesc('id')->get(), 'resizeAvailable' => $processor->available()]);
    }

    public function store(Request $request, BlogPost $blogPost, SaveBlogMedia $save)
    {
        $data = $request->validate(SaveBlogMedia::rules() + ['image' => BlogMediaStorage::UPLOAD_RULES]);

        return $this->write($request, $blogPost, fn () => $save->handle($blogPost, $request->user(), $data, $request->file('image')));
    }

    public function update(Request $request, BlogPost $blogPost, int $media, SaveBlogMedia $save)
    {
        $data = $request->validate(SaveBlogMedia::rules());
        $blogPost->media()->findOrFail($media);

        return $this->write($request, $blogPost, fn () => $save->handle($blogPost, $request->user(), $data, mediaId: $media));
    }

    public function archive(Request $request, BlogPost $blogPost, int $media, SaveBlogMedia $save)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $blogPost->media()->findOrFail($media);

        return $this->write($request, $blogPost, fn () => $save->handle($blogPost, $request->user(), $data, mediaId: $media, archive: true));
    }

    private function write(Request $request, BlogPost $post, callable $operation)
    {
        try {
            $operation();
        } catch (ValidationException $error) {
            throw $error;
        } catch (BlogPostConflict $error) {
            if ($request->expectsJson()) {
                return response()->json(['error' => ['code' => 'revision_conflict', 'message' => $error->getMessage()]], 409);
            }

            return back()->withInput()->withErrors(['revision' => $error->getMessage()]);
        } catch (Throwable $error) {
            Log::error('blog.media_write_failed', ['exception' => $error::class]);
            if ($request->expectsJson()) {
                return response()->json(['error' => ['code' => 'media_save_failed', 'message' => 'Media belum tersimpan. Coba lagi.']], 500);
            }

            return back()->withInput()->withErrors(['image' => 'Media belum tersimpan. File sebelumnya tetap ada. Pilih ulang file lalu coba lagi.']);
        }

        return redirect()->route('admin.blog-media.index', $post)->with('success', 'Media disimpan. Periksa hasil crop, lalu kembali ke editor dengan data terbaru.');
    }
}
