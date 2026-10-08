<?php

namespace Tests\Feature;

use App\Actions\Blog\ChangeBlogPostArchive;
use App\Actions\Blog\RecordBlogPostChange;
use App\Actions\Blog\SaveBlogPost;
use App\Exceptions\BlogPostConflict;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogMediaStorage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BlogWriteSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_create_uses_safe_payload_and_records_actor_without_copying_body_or_path(): void
    {
        $actor = $this->admin();
        $response = $this->actingAs($actor)->post(route('admin.blog-posts.store'), $this->payload() + [
            'actor_id' => 999, 'revision' => 999, 'slug' => 'forged', 'view_count' => 100,
            'featured_image' => $this->image('article.png'),
        ]);
        $response->assertRedirect(route('admin.blog-posts.index'));
        $post = BlogPost::sole();
        $this->assertSame(1, $post->revision);
        $this->assertSame(0, $post->view_count);
        $this->assertSame('Qammaris Editorial', $post->author);
        $this->assertNotSame('forged', $post->slug);
        $this->assertNotNull($post->content_updated_at);
        Storage::disk('public')->assertExists($post->featured_image);
        $row = DB::table('blog_post_changes')->sole();
        $this->assertSame($actor->id, $row->actor_id);
        $this->assertSame('admin', $row->actor_type);
        $this->assertSame('created', $row->action);
        $this->assertStringNotContainsString('Private article body', $row->after);
        $this->assertStringNotContainsString($post->featured_image, $row->after);
        $this->assertStringContainsString(hash('sha256', $post->content), $row->after);
    }

    public function test_successful_replacement_retains_legacy_file_slug_and_id(): void
    {
        $post = $this->postRecord();
        $id = $post->id;
        $slug = $post->slug;
        $this->actingAs($this->admin())->put(route('admin.blog-posts.update', $post),
            $this->payload(['title' => 'Renamed article', 'revision' => 1]) + ['featured_image' => $this->image()])
            ->assertRedirect(route('admin.blog-posts.index'));
        $post->refresh();
        $this->assertSame($id, $post->id);
        $this->assertSame($slug, $post->slug);
        $this->assertSame(2, $post->revision);
        $this->assertSame('public', $post->featured_image_disk);
        $this->assertStringStartsWith('/storage/blog/', parse_url($post->featured_image_url, PHP_URL_PATH));
        Storage::disk('public')->assertExists(['blog/old.jpg', $post->featured_image]);
        $this->assertSame(1, DB::table('blog_post_changes')->count());
    }

    public function test_storage_failure_keeps_current_article_and_file(): void
    {
        $post = $this->postRecord();
        $this->mock(BlogMediaStorage::class, function ($mock) {
            $mock->shouldReceive('store')->once()->andThrow(new RuntimeException('Untrusted internal error'));
            $mock->shouldNotReceive('discard');
        });
        $this->actingAs($this->admin())->putJson(route('admin.blog-posts.update', $post),
            $this->payload(['title' => 'Unsafe change', 'revision' => 1]) + ['featured_image' => $this->image()])
            ->assertStatus(500)->assertJsonPath('error.code', 'save_failed')->assertDontSee('Untrusted internal error');
        $this->assertSame('Original article', $post->fresh()->title);
        $this->assertSame(1, $post->fresh()->revision);
        Storage::disk('public')->assertExists('blog/old.jpg');
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_unverified_storage_write_is_rejected_and_compensated(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        Storage::shouldReceive('disk')->with('broken')->andReturn($disk);
        $disk->shouldReceive('putFileAs')->once()->andReturn('blog/bad.jpg');
        $disk->shouldReceive('exists')->with('blog/bad.jpg')->once()->andReturn(false);
        $disk->shouldReceive('delete')->with('blog/bad.jpg')->once()->andReturn(true);
        config(['media.blog_disk' => 'broken']);
        $this->expectException(RuntimeException::class);
        app(BlogMediaStorage::class)->store($this->image());
    }

    public function test_audit_failure_rolls_back_replacement_and_removes_only_new_upload(): void
    {
        $post = $this->postRecord();
        $audit = Mockery::mock(RecordBlogPostChange::class)->makePartial();
        $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $this->app->instance(RecordBlogPostChange::class, $audit);
        $this->actingAs($this->admin())->putJson(route('admin.blog-posts.update', $post),
            $this->payload(['revision' => 1]) + ['featured_image' => $this->image()])->assertStatus(500);
        $this->assertSame('storage/blog/old.jpg', $post->fresh()->featured_image);
        $this->assertSame(1, $post->fresh()->revision);
        $this->assertSame(['blog/old.jpg'], Storage::disk('public')->allFiles('blog'));
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_create_audit_failure_leaves_no_post_or_upload(): void
    {
        $this->mock(RecordBlogPostChange::class, fn ($mock) => $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable')));
        $this->actingAs($this->admin())->postJson(route('admin.blog-posts.store'),
            $this->payload() + ['featured_image' => $this->image()])->assertStatus(500);
        $this->assertDatabaseCount('blog_posts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_stale_revision_cannot_overwrite_content_or_attach_an_uploaded_file(): void
    {
        $post = $this->postRecord();
        $actor = $this->admin();
        app(SaveBlogPost::class)->handle($this->payload(['title' => 'Other session', 'revision' => 1]), $actor, $post);
        $this->actingAs($actor)->putJson(route('admin.blog-posts.update', $post),
            $this->payload(['title' => 'Stale session', 'revision' => 1]) + ['featured_image' => $this->image()])
            ->assertStatus(409)->assertJsonPath('error.code', 'revision_conflict');
        $this->assertSame('Other session', $post->fresh()->title);
        $this->assertSame(2, $post->fresh()->revision);
        $this->assertSame(['blog/old.jpg'], Storage::disk('public')->allFiles('blog'));
        $this->assertDatabaseCount('blog_post_changes', 1);
    }

    public function test_browser_conflict_retains_submitted_copy_and_old_revision(): void
    {
        $post = $this->postRecord();
        $post->revision = 2;
        $post->save();
        $url = route('admin.blog-posts.edit', $post);
        $this->actingAs($this->admin())->from($url)->put(route('admin.blog-posts.update', $post),
            $this->payload(['title' => 'My unsaved copy', 'revision' => 1]))
            ->assertRedirect($url)->assertSessionHasErrors('revision')->assertSessionHas('_old_input.title', 'My unsaved copy');
        $this->get($url)->assertSee('Muat ulang data terbaru')->assertSee('name="revision" value="1"', false);
        $this->get($url)->assertSee('name="revision" value="2"', false);
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_missing_or_invalid_revision_is_rejected(): void
    {
        $post = $this->postRecord();
        $this->actingAs($this->admin())->putJson(route('admin.blog-posts.update', $post), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->deleteJson(route('admin.blog-posts.destroy', $post), ['revision' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_noop_and_views_do_not_advance_revision_editorial_date_or_audit(): void
    {
        $post = $this->postRecord();
        $actor = $this->admin();
        $data = $post->only(['title', 'excerpt', 'content', 'category', 'author', 'meta_description', 'is_published']);
        $data['published_at'] = $post->published_at->format('Y-m-d\TH:i');
        $data['revision'] = 1;
        $this->get(route('blog.show', $post))->assertOk();
        $this->travel(2)->hours();
        app(SaveBlogPost::class)->handle($data, $actor, $post);
        $post->refresh();
        $this->assertSame(1, $post->revision);
        $this->assertNull($post->content_updated_at);
        $this->assertSame(1, $post->view_count);
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_editorial_date_changes_only_for_editorial_fields(): void
    {
        $post = $this->postRecord();
        $actor = $this->admin();
        app(SaveBlogPost::class)->handle($this->payload(['title' => 'New content', 'revision' => 1, 'is_published' => true]), $actor, $post);
        $editorialAt = $post->fresh()->content_updated_at->toAtomString();
        $this->travel(1)->day();
        $data = $post->fresh()->only(['title', 'excerpt', 'content', 'category', 'author', 'meta_description']);
        app(SaveBlogPost::class)->handle($data + ['is_published' => false, 'revision' => 2], $actor, $post);
        $this->assertSame($editorialAt, $post->fresh()->content_updated_at->toAtomString());
        $this->assertSame(3, $post->fresh()->revision);
        $this->assertNotNull($post->fresh()->published_at);
    }

    public function test_archive_removes_all_public_visibility_and_retains_record_media_and_dates(): void
    {
        $post = $this->postRecord();
        $publishedAt = $post->published_at->toAtomString();
        cache()->put('sitemap.xml', 'old sitemap', 3600);
        $this->actingAs($this->admin())->delete(route('admin.blog-posts.destroy', $post), ['revision' => 1])
            ->assertRedirect(route('admin.blog-posts.index', ['status' => 'archived']));
        $post->refresh();
        $this->assertNotNull($post->archived_at);
        $this->assertSame(2, $post->revision);
        $this->assertSame($publishedAt, $post->published_at->toAtomString());
        $this->assertFalse($post->isPubliclyVisible());
        $this->assertFalse(BlogPost::published()->whereKey($post->id)->exists());
        $this->get(route('blog.show', $post))->assertNotFound();
        $this->get(route('blog.index'))->assertDontSee('Original article');
        $this->get(route('blog.category', 'tips'))->assertDontSee('Original article');
        $this->get('/sitemap.xml')->assertDontSee(route('blog.show', $post));
        $this->get(route('admin.blog-posts.index'))->assertDontSee('Original article');
        $this->get(route('admin.blog-posts.index', ['status' => 'archived']))->assertSee('Original article')->assertSee('Pulihkan draft');
        Storage::disk('public')->assertExists('blog/old.jpg');
        $this->assertDatabaseCount('blog_posts', 1);
        $this->assertSame('archived', DB::table('blog_post_changes')->sole()->action);
    }

    public function test_restore_returns_a_draft_and_never_republishes(): void
    {
        $post = $this->postRecord();
        $actor = $this->admin();
        app(ChangeBlogPostArchive::class)->handle($post, 1, true, $actor);
        $this->actingAs($actor)->patch(route('admin.blog-posts.restore', $post), ['revision' => 2])->assertRedirect(route('admin.blog-posts.index'));
        $post->refresh();
        $this->assertNull($post->archived_at);
        $this->assertFalse($post->is_published);
        $this->assertSame(3, $post->revision);
        $this->assertNotNull($post->published_at);
        $this->get(route('blog.show', $post))->assertNotFound();
        $this->get(route('admin.blog-posts.edit', $post))->assertOk();
        Storage::disk('public')->assertExists('blog/old.jpg');
        $this->assertDatabaseCount('blog_post_changes', 2);
    }

    public function test_archived_article_cannot_be_edited_and_stale_archive_or_restore_fails(): void
    {
        $post = $this->postRecord();
        $actor = $this->admin();
        app(ChangeBlogPostArchive::class)->handle($post, 1, true, $actor);
        $this->actingAs($actor)->putJson(route('admin.blog-posts.update', $post), $this->payload(['revision' => 2]))->assertUnprocessable();
        $this->deleteJson(route('admin.blog-posts.destroy', $post), ['revision' => 1])->assertStatus(409);
        $this->patchJson(route('admin.blog-posts.restore', $post), ['revision' => 1])->assertStatus(409);
        app(ChangeBlogPostArchive::class)->handle($post, 2, true, $actor);
        $this->assertSame(2, $post->fresh()->revision);
        $this->assertDatabaseCount('blog_post_changes', 1);
    }

    public function test_archive_audit_failure_rolls_back_visibility(): void
    {
        $post = $this->postRecord();
        $audit = Mockery::mock(RecordBlogPostChange::class)->makePartial();
        $audit->shouldReceive('handle')->andThrow(new RuntimeException('Audit failure'));
        $this->app->instance(RecordBlogPostChange::class, $audit);
        $this->actingAs($this->admin())->deleteJson(route('admin.blog-posts.destroy', $post), ['revision' => 1])->assertStatus(500);
        $this->assertTrue($post->fresh()->isPubliclyVisible());
        $this->assertSame(1, $post->fresh()->revision);
        Storage::disk('public')->assertExists('blog/old.jpg');
    }

    public function test_customer_cannot_save_archive_or_restore(): void
    {
        $post = $this->postRecord();
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->post(route('admin.blog-posts.store'), $this->payload())->assertForbidden();
        $this->put(route('admin.blog-posts.update', $post), $this->payload(['revision' => 1]))->assertForbidden();
        $this->delete(route('admin.blog-posts.destroy', $post), ['revision' => 1])->assertForbidden();
        $this->patch(route('admin.blog-posts.restore', $post), ['revision' => 1])->assertForbidden();
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_uploaded_extension_and_dimensions_are_validated_before_write(): void
    {
        $post = $this->postRecord();
        $this->actingAs($this->admin())->put(route('admin.blog-posts.update', $post),
            $this->payload(['revision' => 1]) + ['featured_image' => $this->image('bad.gif')])
            ->assertSessionHasErrors('featured_image');
        $this->post(route('admin.blog-posts.store'), $this->payload() + ['featured_image' => $this->image('huge.png', 6001)])
            ->assertSessionHasErrors('featured_image');
        $this->assertSame(1, $post->fresh()->revision);
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_blank_optional_author_and_description_preserve_existing_author_and_fall_back_safely(): void
    {
        $post = $this->postRecord();
        $this->actingAs($this->admin())->put(route('admin.blog-posts.update', $post),
            $this->payload(['revision' => 1, 'author' => '', 'meta_description' => '']))->assertRedirect(route('admin.blog-posts.index'));
        $this->assertSame('Original author', $post->fresh()->author);
        $this->assertNull($post->fresh()->meta_description);
    }

    public function test_direct_operation_requires_revision_even_for_a_cached_model(): void
    {
        $post = $this->postRecord();
        $this->expectException(BlogPostConflict::class);
        app(SaveBlogPost::class)->handle($this->payload(), $this->admin(), $post);
    }

    public function test_cache_outage_after_commit_does_not_remove_the_new_image(): void
    {
        // Exercise a real commit on a separate in-memory connection; never commit the suite's shared fixtures.
        $original = DB::getDefaultConnection();
        config(['database.connections.blog_commit_test' => array_replace(config('database.connections.sqlite'), ['database' => ':memory:'])]);
        DB::setDefaultConnection('blog_commit_test');
        try {
            Artisan::call('migrate', ['--database' => 'blog_commit_test', '--force' => true]);
            Cache::shouldReceive('forget')->with('sitemap.xml')->once()->andThrow(new RuntimeException('Cache unavailable'));
            $post = app(SaveBlogPost::class)->handle($this->payload(), $this->admin(), image: $this->image());
            Storage::disk('public')->assertExists($post->featured_image);
            $this->assertDatabaseCount('blog_post_changes', 1);
        } finally {
            DB::purge('blog_commit_test');
            DB::setDefaultConnection($original);
        }
    }

    public function test_additive_migration_and_replay_preserve_all_legacy_article_columns(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.blog_migration_test' => array_replace(config('database.connections.sqlite'), ['database' => ':memory:'])]);
        DB::setDefaultConnection('blog_migration_test');
        try {
            Artisan::call('migrate', [
                '--database' => 'blog_migration_test', '--force' => true,
                '--path' => 'database/migrations/2025_11_22_091511_create_blog_posts_table.php',
            ]);
            foreach (['Tips', 'Review', 'Panduan', 'Berita'] as $index => $category) {
                DB::table('blog_posts')->insert([
                    'id' => 70 + $index, 'title' => 'Legacy '.$category, 'slug' => 'legacy-'.$index,
                    'excerpt' => 'Existing excerpt', 'content' => '<p>Existing content</p>',
                    'featured_image' => ['images/legacy.jpg', 'storage/blog/legacy.jpg', '/images/legacy.jpg', 'https://example.test/legacy.jpg'][$index],
                    'author' => 'Existing author', 'category' => $category, 'is_published' => $index !== 0,
                    'published_at' => $index === 0 ? null : '2026-10-01 12:00:00', 'view_count' => 23,
                    'meta_description' => 'Existing metadata', 'created_at' => '2026-09-01 12:00:00', 'updated_at' => '2026-10-01 12:00:00',
                ]);
            }
            $before = DB::table('blog_posts')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            $columns = array_keys($before[0]);
            $migration = ['--database' => 'blog_migration_test', '--force' => true, '--path' => 'database/migrations/2026_10_07_000002_add_blog_write_safety.php'];
            $this->assertSame(0, Artisan::call('migrate', $migration));
            $this->assertSame(0, Artisan::call('migrate', $migration));
            $after = DB::table('blog_posts')->select($columns)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            $this->assertSame($before, $after);
            $this->assertSame(4, DB::table('blog_posts')->where('revision', 1)->whereNull('archived_at')->whereNull('content_updated_at')->whereNull('featured_image_disk')->count());
            $this->assertDatabaseCount('blog_post_changes', 0);
            $this->assertSame(1, DB::table('migrations')->where('migration', '2026_10_07_000002_add_blog_write_safety')->count());
        } finally {
            DB::purge('blog_migration_test');
            DB::setDefaultConnection($original);
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function image(string $name = 'new.png', int $width = 1): UploadedFile
    {
        // Fixed PNG fixture keeps regression tests portable on CI/local PHP without GD.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        if ($width !== 1) {
            $png = substr_replace($png, pack('N', $width), 16, 4);
            $png = substr_replace($png, pack('N', crc32(substr($png, 12, 17))), 29, 4);
        }

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function postRecord(): BlogPost
    {
        Storage::disk('public')->put('blog/old.jpg', 'retained legacy bytes');

        return BlogPost::create($this->payload([
            'title' => 'Original article', 'author' => 'Original author',
            'published_at' => now()->startOfMinute()->subDay(), 'is_published' => true,
            'featured_image' => 'storage/blog/old.jpg',
        ]));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Safe new article', 'excerpt' => 'Private excerpt', 'content' => '<p>Private article body</p>',
            'category' => 'Tips', 'author' => null, 'meta_description' => null, 'is_published' => false, 'published_at' => null,
        ], $overrides);
    }
}
