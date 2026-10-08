<?php

namespace Tests\Feature;

use App\Actions\Blog\AssignBlogDraft;
use App\Models\BlogAutomationActor;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlogDraftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_admin_can_assign_reassign_and_revoke_without_changing_content_or_editorial_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = BlogAutomationActor::create(['name' => 'First']);
        $second = BlogAutomationActor::create(['name' => 'Second']);
        $post = $this->postRecord();
        $before = $post->fresh()->only(['slug', 'content', 'author', 'content_updated_at']);
        $url = route('admin.blog-automation.update', $post);
        $this->actingAs($admin)->get(route('admin.blog-posts.edit', $post))->assertSee('Kelola akses agent');
        $this->get(route('admin.blog-automation.edit', $post))->assertOk()->assertSee('Tanpa agent')->assertDontSee('name="token"', false);
        foreach ([$first->id, $second->id, null] as $offset => $id) {
            $this->put($url, ['revision' => $offset + 1, 'automation_actor_id' => $id])->assertRedirect()->assertSessionHas('success');
            $post->refresh();
            $this->assertSame($id, $post->automation_actor_id);
            $this->assertSame($offset + 2, $post->revision);
            $this->assertSame($before, $post->only(array_keys($before)));
        }
        $this->assertDatabaseCount('blog_post_changes', 3);
        $this->assertSame(['admin'], DB::table('blog_post_changes')->distinct()->pluck('actor_type')->all());
        $this->assertSame(['assigned'], DB::table('blog_post_changes')->distinct()->pluck('action')->all());
    }

    public function test_stale_revision_inactive_actor_and_published_or_archived_articles_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = BlogAutomationActor::create(['name' => 'Inactive', 'is_active' => false]);
        $post = $this->postRecord();
        $url = route('admin.blog-automation.update', $post);
        $this->actingAs($admin)->from(route('admin.blog-automation.edit', $post))
            ->put($url, ['revision' => 99, 'automation_actor_id' => $actor->id])->assertSessionHasErrors('revision');
        $this->put($url, ['revision' => 1, 'automation_actor_id' => $actor->id])->assertSessionHasErrors('automation_actor_id');
        $actor->update(['is_active' => true]);
        $post->update(['is_published' => true, 'published_at' => now()->addDay()]);
        $this->put($url, ['revision' => 1, 'automation_actor_id' => $actor->id])->assertSessionHasErrors('automation_actor_id');
        $post->forceFill(['is_published' => false, 'archived_at' => now()])->save();
        $this->put($url, ['revision' => 1, 'automation_actor_id' => $actor->id])->assertSessionHasErrors('automation_actor_id');
        $this->assertDatabaseCount('blog_post_changes', 0);
        $this->assertNull($post->fresh()->automation_actor_id);
    }

    public function test_same_assignment_is_noop_and_non_admin_cannot_assign(): void
    {
        $post = $this->postRecord();
        $actor = BlogAutomationActor::create(['name' => 'Writer']);
        $this->actingAs(User::factory()->create(['role' => 'staff']))->put(route('admin.blog-automation.update', $post), ['revision' => 1, 'automation_actor_id' => $actor->id])->assertForbidden();
        $saved = app(AssignBlogDraft::class)->handle($post, User::factory()->create(['role' => 'admin']), 1, null);
        $this->assertSame(1, $saved->revision);
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    private function postRecord(): BlogPost
    {
        return BlogPost::create(['title' => 'Original draft', 'excerpt' => '', 'content' => '<p>Keep body</p>', 'author' => 'Original author', 'category' => 'Tips', 'is_published' => false]);
    }
}
