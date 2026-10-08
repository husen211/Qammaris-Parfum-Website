<?php

namespace App\Support;

use App\Models\BlogAutomationActor;
use App\Models\BlogPost;
use App\Models\User;

final class BlogWriteAccess
{
    public static function assert(User|BlogAutomationActor $actor, ?BlogPost $post = null, bool $lock = false): void
    {
        if ($actor instanceof User) {
            abort_unless($actor->exists && $actor->role === 'admin', 403);

            return;
        }

        $query = BlogAutomationActor::whereKey($actor->id);
        $current = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($current?->is_active, 403);
        if ($post !== null) {
            abort_unless((int) $post->automation_actor_id === $actor->id && ! $post->is_published && $post->archived_at === null, 403);
        }
    }
}
