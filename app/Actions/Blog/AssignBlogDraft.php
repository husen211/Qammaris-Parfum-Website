<?php

namespace App\Actions\Blog;

use App\Exceptions\BlogPostConflict;
use App\Models\BlogAutomationActor;
use App\Models\BlogPost;
use App\Models\User;
use App\Support\BlogWriteAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignBlogDraft
{
    public function __construct(private RecordBlogPostChange $audit) {}

    public function handle(BlogPost $post, User $admin, int $revision, ?int $actorId): BlogPost
    {
        BlogWriteAccess::assert($admin);

        return DB::transaction(function () use ($post, $admin, $revision, $actorId) {
            $post = BlogPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
            if ($post->revision !== $revision) {
                throw new BlogPostConflict;
            }
            if ($post->is_published || $post->archived_at) {
                throw ValidationException::withMessages(['automation_actor_id' => 'Akses agent hanya tersedia untuk draft. Simpan sebagai draft terlebih dahulu.']);
            }
            if ($actorId !== null && ! BlogAutomationActor::whereKey($actorId)->lockForUpdate()->first()?->is_active) {
                throw ValidationException::withMessages(['automation_actor_id' => 'Pilih agent yang aktif.']);
            }
            if ($post->automation_actor_id === $actorId) {
                return $post;
            }
            $before = $this->audit->snapshot($post);
            $post->automation_actor_id = $actorId;
            $post->revision++;
            // Assignment is workflow metadata, not an editorial change.
            $post->save();
            $this->audit->handle($post, $admin, 'assigned', $before);

            return $post;
        }, attempts: 3);
    }
}
