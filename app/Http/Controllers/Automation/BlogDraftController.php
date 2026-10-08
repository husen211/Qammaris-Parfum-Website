<?php

namespace App\Http\Controllers\Automation;

use App\Actions\Blog\IdempotentBlogWrite;
use App\Actions\Blog\SaveBlogMedia;
use App\Actions\Blog\SaveBlogPost;
use App\Http\Controllers\Controller;
use App\Models\BlogMedia;
use App\Models\BlogPost;
use App\Services\BlogMediaStorage;
use App\Support\BlogAutomationFields;
use App\Support\BlogWriteAccess;
use Illuminate\Http\Request;

class BlogDraftController extends Controller
{
    public function index(Request $request)
    {
        $input = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']]);
        $page = BlogPost::where('automation_actor_id', $request->user()->id)->where('is_published', false)->whereNull('archived_at')
            ->with('editorialCategory')->orderByDesc('id')->paginate($input['per_page'] ?? 20);

        return response()->json(['data' => $page->getCollection()->map(fn ($post) => $post->only(['id', 'title', 'slug', 'excerpt', 'category', 'author', 'revision']) + ['status' => 'draft']), 'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function show(Request $request, int $id)
    {
        return response()->json(['data' => $this->resource($this->owned($request, $id)), 'meta' => (object) []]);
    }

    public function store(Request $request, SaveBlogPost $save, IdempotentBlogWrite $idempotency)
    {
        BlogAutomationFields::rejectUnknown($request->all(), BlogAutomationFields::EDITORIAL);
        $data = $request->validate(BlogAutomationFields::rules());
        [$post, $meta] = $idempotency->handle($request->user(), 'create-draft', $request->header('Idempotency-Key'), $data, null,
            fn ($complete) => $save->handle($data, $request->user(), afterSave: $complete),
            fn ($id) => $this->owned($request, $id));

        return response()->json(['data' => $this->resource($post), 'meta' => $meta], $meta['replayed'] ? 200 : 201)
            ->header('Location', url('/api/automation/v1/blog-posts/'.$post->id));
    }

    public function update(Request $request, int $id, SaveBlogPost $save)
    {
        $post = $this->owned($request, $id);
        BlogAutomationFields::rejectUnknown($request->all(), [...BlogAutomationFields::EDITORIAL, 'revision']);
        $data = $request->validate(BlogAutomationFields::rules() + ['revision' => ['required', 'integer', 'min:1']]);
        $post = $save->handle($data, $request->user(), $post);

        return response()->json(['data' => $this->resource($post), 'meta' => (object) []]);
    }

    public function media(Request $request, int $id, SaveBlogMedia $save, IdempotentBlogWrite $idempotency)
    {
        $post = $this->owned($request, $id);
        BlogAutomationFields::rejectUnknown($request->all(), [...array_keys(SaveBlogMedia::rules()), 'image']);
        $validated = $request->validate(SaveBlogMedia::rules() + ['image' => BlogMediaStorage::UPLOAD_RULES]);
        unset($validated['image']);
        [$media, $meta] = $idempotency->handle($request->user(), 'upload-media:'.$id, $request->header('Idempotency-Key'), $validated, $request->file('image'),
            fn ($complete) => $save->handle($post, $request->user(), $validated, $request->file('image'), afterSave: $complete),
            fn ($mediaId) => $this->owned($request, $id)->media()->whereNull('archived_at')->findOrFail($mediaId));

        return response()->json(['data' => $this->mediaResource($media) + ['revision' => $post->fresh()->revision], 'meta' => $meta], $meta['replayed'] ? 200 : 201);
    }

    private function owned(Request $request, int $id): BlogPost
    {
        $post = BlogPost::findOrFail($id);
        BlogWriteAccess::assert($request->user(), $post);

        return $post;
    }

    private function resource(BlogPost $post): array
    {
        return $post->only(array_diff(BlogAutomationFields::EDITORIAL, ['tag_ids'])) + [
            'id' => $post->id, 'slug' => $post->slug, 'revision' => $post->revision, 'status' => 'draft',
            'tag_ids' => $post->tags->pluck('id')->all(),
            'media' => $post->media()->whereNull('archived_at')->orderBy('id')->get()->map(fn ($media) => $this->mediaResource($media))->all(),
        ];
    }

    private function mediaResource(BlogMedia $media): array
    {
        return $media->only(['id', 'alt', 'caption', 'credit', 'source_url', 'license', 'width', 'height', 'crop', 'focal_x', 'focal_y']) + ['url' => $media->url, 'srcset' => $media->srcset()];
    }
}
