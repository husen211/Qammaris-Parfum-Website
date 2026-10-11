<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\AssignBlogDraft;
use App\Exceptions\BlogPostConflict;
use App\Http\Controllers\Controller;
use App\Models\BlogAutomationActor;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminBlogAutomationController extends Controller
{
    public function edit(BlogPost $blogPost)
    {
        $actors = BlogAutomationActor::where('is_active', true)->orWhere('id', $blogPost->automation_actor_id)->orderBy('name')->get();

        return view('admin.blog-posts.automation', compact('blogPost', 'actors'));
    }

    public function update(Request $request, BlogPost $blogPost, AssignBlogDraft $assign)
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'automation_actor_id' => ['present', 'nullable', 'integer', 'min:1']]);
        try {
            $assign->handle($blogPost, $request->user(), (int) $data['revision'], isset($data['automation_actor_id']) ? (int) $data['automation_actor_id'] : null);
        } catch (BlogPostConflict $error) {
            return back()->withInput()->withErrors(['revision' => $error->getMessage()]);
        } catch (ValidationException $error) {
            throw $error;
        } catch (Throwable $error) {
            Log::error('blog.assignment_failed', ['exception' => $error::class]);

            return back()->withInput()->withErrors(['automation_actor_id' => 'Akses belum tersimpan. Coba lagi.']);
        }

        return back()->with('success', 'Akses draft diperbarui. Isi artikel tidak diubah.');
    }
}
