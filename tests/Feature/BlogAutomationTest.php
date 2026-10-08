<?php

namespace Tests\Feature;

use App\Actions\Blog\AssignBlogDraft;
use App\Actions\Blog\RecordBlogPostChange;
use App\Actions\Blog\SaveBlogMedia;
use App\Actions\Blog\SaveBlogPost;
use App\Models\BlogAutomationActor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BlogAutomationTest extends TestCase
{
    use RefreshDatabase;

    private BlogAutomationActor $actor;

    private string $token;

    private const BASE = '/api/automation/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['blog_automation.enabled' => true]);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Storage::fake('public');
        $this->actor = BlogAutomationActor::create(['name' => 'Local test writer']);
        $this->token = $this->actor->createToken('test', BlogAutomationActor::ABILITIES, now()->addDays(90))->plainTextToken;
    }

    public function test_disabled_api_is_explicit_and_does_not_create_credentials(): void
    {
        config(['blog_automation.enabled' => false]);
        $this->api('GET', '/blog-posts')->assertStatus(503)->assertJsonPath('error.code', 'automation_disabled');
        $this->api('GET', '/blog-posts', token: '')->assertStatus(503)->assertJsonPath('error.code', 'automation_disabled');
        $this->artisan('blog:automation', ['action' => 'create', '--name' => 'Blocked'])->assertFailed();
        $this->assertDatabaseCount('blog_automation_actors', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_authentication_rejects_missing_invalid_expired_revoked_and_non_expiring_tokens(): void
    {
        $this->api('GET', '/blog-posts', token: '')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->api('GET', '/blog-posts', token: 'invalid')->assertUnauthorized();
        $expired = $this->actor->createToken('expired', BlogAutomationActor::ABILITIES, now()->subMinute());
        $this->api('GET', '/blog-posts', token: $expired->plainTextToken)->assertUnauthorized();
        $old = $this->actor->createToken('old', BlogAutomationActor::ABILITIES, now()->addDays(90));
        $old->accessToken->forceFill(['created_at' => now()->subDays(91)])->save();
        $this->api('GET', '/blog-posts', token: $old->plainTextToken)->assertUnauthorized();
        $revoked = $this->actor->createToken('revoked', BlogAutomationActor::ABILITIES, now()->addDays(90));
        $revoked->accessToken->delete();
        $this->api('GET', '/blog-posts', token: $revoked->plainTextToken)->assertUnauthorized();
        $unbounded = $this->actor->createToken('unbounded', BlogAutomationActor::ABILITIES);
        $this->api('GET', '/blog-posts', token: $unbounded->plainTextToken)->assertUnauthorized();
        $stored = $this->actor->tokens()->where('name', 'test')->sole();
        $this->assertNotSame($this->token, $stored->token);
        $this->assertSame(64, strlen($stored->token));
    }

    public function test_human_session_and_inactive_machine_cannot_access_api(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->api('GET', '/blog-posts', token: '')->assertUnauthorized();
        $this->actor->update(['is_active' => false]);
        $this->api('GET', '/blog-posts')->assertForbidden();
    }

    public function test_scopes_are_enforced_for_each_route_family(): void
    {
        $read = $this->actor->createToken('reader', ['blog:read'], now()->addDays(90))->plainTextToken;
        $post = $this->draft();
        $this->api('GET', '/blog-posts', token: $read)->assertOk();
        $this->api('GET', '/blog-posts/'.$post->id, token: $read)->assertOk();
        $this->api('GET', '/blog-taxonomy', token: $read)->assertOk();
        $this->api('POST', '/blog-posts', ['title' => 'Blocked'], $read, 'create-blocked')->assertForbidden();
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1], $read)->assertForbidden();
        $this->api('POST', '/blog-posts/'.$post->id.'/media', [], $read, 'media-blocked')->assertForbidden();
        $this->api('GET', '/products', token: $read)->assertForbidden();
        $catalog = $this->actor->createToken('catalog', ['catalog:read'], now()->addDays(90))->plainTextToken;
        $this->api('GET', '/products', token: $catalog)->assertOk();
        $this->api('GET', '/blog-posts', token: $catalog)->assertForbidden();
    }

    public function test_create_sanitizes_and_attributes_a_draft_without_exposing_it_publicly(): void
    {
        $payload = ['title' => 'Agent journal', 'excerpt' => 'Summary', 'content' => '<h2>Guide</h2><p onclick="bad()">Private body</p><script>bad()</script>'];
        $response = $this->api('POST', '/blog-posts', $payload, key: 'create-article-1')->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.revision', 1);
        $post = BlogPost::sole();
        $this->assertFalse($post->is_published);
        $this->assertNull($post->published_at);
        $this->assertSame($this->actor->id, $post->automation_actor_id);
        $this->assertSame('Qammaris Editorial', $post->author);
        $this->assertSame('<h2>Guide</h2><p>Private body</p>', $post->content);
        $row = DB::table('blog_post_changes')->sole();
        $this->assertSame('machine', $row->actor_type);
        $this->assertSame($this->actor->id, $row->actor_id);
        $this->assertStringNotContainsString('Private body', $row->after);
        $this->assertStringNotContainsString($this->token, $row->after);
        $response->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Location', url(self::BASE.'/blog-posts/'.$post->id));
        $this->get('/blog/'.$post->slug)->assertNotFound();
        $this->get('/blog')->assertDontSee('Agent journal');
    }

    public function test_agent_rejects_publication_actor_and_unknown_fields_instead_of_ignoring_them(): void
    {
        foreach (['is_published' => true, 'published_at' => now()->toAtomString(), 'status' => 'published', 'actor_id' => 1, 'automation_actor_id' => 1, 'archived_at' => null, 'slug' => 'forged', 'view_count' => 999, 'is_featured' => true, 'price' => 1, 'featured_image' => 'https://internal.invalid'] as $field => $value) {
            $this->api('POST', '/blog-posts', ['title' => 'Blocked', $field => $value], key: 'forbidden-'.$field)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        }
        $this->assertDatabaseCount('blog_posts', 0);
        $post = $this->draft();
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'is_published' => true])->assertUnprocessable();
        $this->assertFalse($post->fresh()->is_published);
        $this->api('DELETE', '/blog-posts/'.$post->id)->assertStatus(405);
        $this->api('POST', '/blog-posts/'.$post->id.'/publish', [])->assertNotFound();
    }

    public function test_agent_only_lists_and_accesses_owned_unpublished_unarchived_drafts(): void
    {
        $own = $this->draft();
        $other = BlogAutomationActor::create(['name' => 'Other']);
        $states = [
            $this->draft(['automation_actor_id' => $other->id]),
            $this->draft(['automation_actor_id' => null]),
            $this->draft(['is_published' => true, 'published_at' => now()->subDay()]),
            $this->draft(['is_published' => true, 'published_at' => now()->addDay()]),
            $this->draft(['archived_at' => now()]),
        ];
        $this->api('GET', '/blog-posts')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id)->assertJsonMissingPath('data.0.content')->assertJsonMissingPath('data.0.media');
        foreach ($states as $post) {
            $this->api('GET', '/blog-posts/'.$post->id)->assertForbidden();
            $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'title' => 'Denied'])->assertForbidden();
            $this->api('POST', '/blog-posts/'.$post->id.'/media', [], key: 'denied-media-'.$post->id)->assertForbidden();
        }
        $this->api('GET', '/blog-posts/999999')->assertNotFound();
    }

    public function test_patch_preserves_omitted_content_tags_slug_and_rejects_stale_revision(): void
    {
        $post = $this->draft(['subtitle' => 'Keep this', 'meta_description' => 'Keep SEO']);
        $tag = BlogTag::create(['name' => 'Fresh', 'slug' => 'fresh', 'is_active' => true]);
        $post->tags()->attach($tag);
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'title' => 'New title'])->assertOk()->assertJsonPath('data.revision', 2);
        $post->refresh();
        $this->assertSame('<p>Original body</p>', $post->content);
        $this->assertSame('Keep this', $post->subtitle);
        $this->assertSame('Keep SEO', $post->meta_description);
        $this->assertSame('draft-article', $post->slug);
        $this->assertSame([$tag->id], $post->tags->pluck('id')->all());
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'title' => 'Stale'])->assertStatus(409)->assertJsonPath('error.code', 'revision_conflict');
        $this->api('PATCH', '/blog-posts/'.$post->id, ['title' => 'Missing revision'])->assertUnprocessable();
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 2, 'tag_ids' => []])->assertOk();
        $this->assertSame(0, $post->tags()->count());
    }

    public function test_create_retry_is_not_duplicated_and_changed_payload_conflicts(): void
    {
        $data = ['title' => 'Idempotent', 'content' => '<p>Article</p>'];
        $first = $this->api('POST', '/blog-posts', $data, key: 'stable-create-key')->assertCreated();
        $this->api('POST', '/blog-posts', array_reverse($data, true), key: 'stable-create-key')->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('meta.replayed', true);
        $this->api('POST', '/blog-posts', ['title' => 'Different'], key: 'stable-create-key')->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->assertDatabaseCount('blog_posts', 1);
        $this->assertDatabaseCount('blog_post_changes', 1);
        $this->assertDatabaseCount('blog_automation_requests', 1);
        $this->api('POST', '/blog-posts', [], key: null)->assertUnprocessable();
        $this->api('POST', '/blog-posts', [], key: 'short')->assertUnprocessable();
    }

    public function test_pending_duplicate_cannot_run_and_keys_are_scoped_by_actor(): void
    {
        $this->api('POST', '/blog-posts', ['title' => 'Scoped'], key: 'same-key-both-actors')->assertCreated();
        DB::table('blog_automation_requests')->update(['state' => 'pending']);
        $this->api('POST', '/blog-posts', ['title' => 'Scoped'], key: 'same-key-both-actors')->assertStatus(409)->assertHeader('Retry-After', '5');
        $other = BlogAutomationActor::create(['name' => 'Other writer']);
        $otherToken = $other->createToken('test', BlogAutomationActor::ABILITIES, now()->addDays(90))->plainTextToken;
        $this->api('POST', '/blog-posts', ['title' => 'Scoped'], $otherToken, 'same-key-both-actors')->assertCreated();
        $this->assertDatabaseCount('blog_posts', 2);
    }

    public function test_create_replay_cannot_read_a_draft_after_owner_publishes_it(): void
    {
        $this->api('POST', '/blog-posts', ['title' => 'Replay guard'], key: 'create-replay-guard')->assertCreated();
        BlogPost::sole()->update(['is_published' => true, 'published_at' => now()->addDay()]);
        $this->api('POST', '/blog-posts', ['title' => 'Replay guard'], key: 'create-replay-guard')->assertForbidden();
    }

    public function test_upload_retry_keeps_one_owned_file_and_can_be_selected_as_hero(): void
    {
        $post = $this->draft();
        $input = $this->mediaInput();
        $first = $this->api('POST', '/blog-posts/'.$post->id.'/media', $input, key: 'upload-media-once')->assertCreated()->assertJsonPath('data.revision', 2);
        $files = Storage::disk('public')->allFiles();
        $this->api('POST', '/blog-posts/'.$post->id.'/media', $this->mediaInput(), key: 'upload-media-once')->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('meta.replayed', true);
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('blog_media', 1);
        $this->assertDatabaseCount('blog_post_changes', 1);
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 2, 'featured_media_id' => $first->json('data.id'), 'content' => '<p>Owned picture</p><div data-qammaris-media="'.$first->json('data.id').'"></div>'])->assertOk();
        $this->assertSame($first->json('data.id'), $post->fresh()->featured_media_id);
        $this->assertNotNull($post->fresh()->featured_image);
        $this->assertFalse($post->fresh()->is_published);
    }

    public function test_upload_rejects_remote_url_unknown_fields_invalid_mime_and_foreign_media_reference(): void
    {
        $post = $this->draft();
        $this->api('POST', '/blog-posts/'.$post->id.'/media', ['url' => 'http://127.0.0.1/private'], key: 'reject-remote-url')->assertUnprocessable();
        $this->api('POST', '/blog-posts/'.$post->id.'/media', array_replace($this->mediaInput(), ['image' => UploadedFile::fake()->create('image.svg', 1, 'image/svg+xml')]), key: 'reject-svg-media')->assertUnprocessable();
        $other = $this->draft();
        $media = app(SaveBlogMedia::class)->handle($other, User::factory()->create(['role' => 'admin']), array_diff_key($this->mediaInput(), ['image' => true]), $this->mediaInput()['image']);
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'featured_media_id' => $media->id])->assertUnprocessable();
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'content' => '<p>Body</p><div data-qammaris-media="'.$media->id.'"></div>'])->assertUnprocessable();
        $this->assertSame(1, $post->fresh()->revision);
    }

    public function test_machine_images_must_use_owned_markers_but_omitted_legacy_content_is_preserved(): void
    {
        $post = $this->draft(['content' => '<p>Legacy</p><img src="https://example.test/old.jpg" alt="Legacy">']);
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 1, 'title' => 'Keep legacy'])->assertOk();
        $this->assertStringContainsString('https://example.test/old.jpg', $post->fresh()->content);
        $this->api('PATCH', '/blog-posts/'.$post->id, ['revision' => 2, 'content' => '<p>New</p><img src="/storage/blog/another-article.jpg" alt="Other">'])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        $this->api('POST', '/blog-posts', ['content' => '<img src="https://example.test/tracking.jpg">'], key: 'reject-hotlinked-image')->assertUnprocessable();
        $this->assertDatabaseCount('blog_posts', 1);
    }

    public function test_failed_idempotency_completion_rolls_back_upload_and_retry_can_succeed(): void
    {
        $post = $this->draft();
        DB::unprepared("CREATE TRIGGER fail_blog_completion BEFORE UPDATE ON blog_automation_requests BEGIN SELECT RAISE(ABORT, 'completion unavailable'); END");
        $this->api('POST', '/blog-posts/'.$post->id.'/media', $this->mediaInput(), key: 'retry-completion-failure')->assertStatus(500)->assertDontSee('completion unavailable');
        $this->assertDatabaseCount('blog_media', 0);
        $this->assertDatabaseCount('blog_post_changes', 0);
        $this->assertDatabaseCount('blog_automation_requests', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(1, $post->fresh()->revision);
        DB::unprepared('DROP TRIGGER fail_blog_completion');
        $this->api('POST', '/blog-posts/'.$post->id.'/media', $this->mediaInput(), key: 'retry-completion-failure')->assertCreated();
    }

    public function test_audit_failure_rolls_back_create_and_does_not_complete_key(): void
    {
        $this->mock(RecordBlogPostChange::class, fn ($mock) => $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Private binding')));
        $this->api('POST', '/blog-posts', ['title' => 'Unsafe'], key: 'audit-create-failure')->assertStatus(500)->assertDontSee('Private binding');
        $this->assertDatabaseCount('blog_posts', 0);
        $this->assertDatabaseCount('blog_automation_requests', 0);
    }

    public function test_lock_time_guard_rejects_owner_publication_after_controller_read(): void
    {
        $post = $this->draft();
        DB::table('blog_posts')->where('id', $post->id)->update(['is_published' => true, 'published_at' => now()]);
        $this->expectException(HttpException::class);
        app(SaveBlogPost::class)->handle(['revision' => 1, 'title' => 'Cannot overwrite'], $this->actor, $post);
    }

    public function test_taxonomy_lookup_excludes_inactive_and_cannot_create_taxonomy(): void
    {
        BlogCategory::where('name', 'Tips')->update(['is_active' => false]);
        BlogTag::create(['name' => 'Inactive tag', 'slug' => 'inactive', 'is_active' => false]);
        $this->api('GET', '/blog-taxonomy')->assertOk()->assertJsonCount(3, 'data.categories')->assertJsonCount(0, 'data.tags');
        $this->api('POST', '/blog-taxonomy', ['name' => 'Forbidden'])->assertStatus(405);
    }

    public function test_lookup_only_reads_public_catalog_and_reflects_current_price_and_availability(): void
    {
        $brand = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $category = Category::create(['name' => 'Eau de Parfum', 'is_active' => true]);
        $product = Product::create(['name' => 'Public fragrance', 'brand_id' => $brand->id, 'category_id' => $category->id, 'publication_status' => 'published', 'is_active' => true, 'availability_source' => 'qammaris_app', 'availability_status' => 'sold_out']);
        $offer = ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 460000, 'is_active' => true]);
        foreach ([['publication_status' => 'draft'], ['qammaris_app_hidden' => true], ['publication_status' => 'archived']] as $override) {
            $copy = $product->replicate(['slug']);
            $copy->forceFill($override + ['name' => 'Excluded'])->save();
        }
        $before = $product->fresh()->getAttributes();
        $this->api('GET', '/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', '460000.00')->assertJsonPath('data.0.availability', 'sold_out');
        $this->assertSame($before, $product->fresh()->getAttributes());
        $offer->update(['price' => 479000]);
        $product->update(['availability_status' => 'available']);
        $this->api('GET', '/products?search=fragrance')->assertJsonPath('data.0.price', '479000.00')->assertJsonPath('data.0.availability', 'available');
        $this->api('POST', '/products', ['price' => 1])->assertStatus(405);
    }

    public function test_actor_rate_limit_is_shared_by_tokens_but_not_another_actor(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->api('GET', '/blog-posts')->assertOk();
        }
        $second = $this->actor->createToken('second', BlogAutomationActor::ABILITIES, now()->addDays(90))->plainTextToken;
        $this->api('GET', '/blog-posts', token: $second)->assertStatus(429)->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
        $other = BlogAutomationActor::create(['name' => 'Other actor']);
        $token = $other->createToken('test', BlogAutomationActor::ABILITIES, now()->addDays(90))->plainTextToken;
        $this->api('GET', '/blog-posts', token: $token)->assertOk();
    }

    public function test_upload_rate_limit_has_ten_requests_per_actor(): void
    {
        $post = $this->draft();
        for ($i = 0; $i < 10; $i++) {
            $this->api('POST', '/blog-posts/'.$post->id.'/media', [], key: 'invalid-media-'.$i)->assertUnprocessable();
        }
        $this->api('POST', '/blog-posts/'.$post->id.'/media', [], key: 'invalid-media-eleven')->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
        $this->api('GET', '/blog-posts')->assertOk();
    }

    public function test_pre_auth_rate_limit_also_bounds_invalid_bearer_requests(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->api('GET', '/blog-posts', token: 'invalid')->assertUnauthorized();
        }
        $this->api('GET', '/blog-posts', token: 'invalid')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_command_issues_bounded_scopes_and_can_revoke_or_disable_without_api_enabled(): void
    {
        $this->artisan('blog:automation', ['action' => 'issue', 'actor' => $this->actor->id, '--name' => 'Reader', '--ability' => ['blog:read']])->assertSuccessful();
        $token = $this->actor->tokens()->where('name', 'Reader')->sole();
        $this->assertSame(['blog:read'], $token->abilities);
        $this->assertSame(90, (int) round(now()->diffInDays($token->expires_at)));
        $this->artisan('blog:automation', ['action' => 'issue', 'actor' => $this->actor->id, '--name' => 'Forbidden', '--ability' => ['*']])->assertFailed();
        config(['blog_automation.enabled' => false]);
        $this->artisan('blog:automation', ['action' => 'revoke', 'actor' => $this->actor->id, '--token' => (string) $token->id])->assertSuccessful();
        $this->assertNull($token->fresh());
        $this->artisan('blog:automation', ['action' => 'disable', 'actor' => $this->actor->id])->assertSuccessful();
        $this->assertFalse($this->actor->fresh()->is_active);
    }

    public function test_assignment_revokes_previous_agent_and_stale_media_upload_after_owner_edit_is_cleaned_up(): void
    {
        $post = $this->draft();
        $other = BlogAutomationActor::create(['name' => 'Replacement']);
        app(AssignBlogDraft::class)->handle($post, User::factory()->create(['role' => 'admin']), 1, $other->id);
        $this->api('GET', '/blog-posts/'.$post->id)->assertForbidden();
        $this->api('GET', '/blog-posts')->assertJsonCount(0, 'data');
        $otherToken = $other->createToken('test', BlogAutomationActor::ABILITIES, now()->addDays(90))->plainTextToken;
        $this->api('GET', '/blog-posts/'.$post->id, token: $otherToken)->assertOk()->assertJsonPath('data.revision', 2);
        $this->api('POST', '/blog-posts/'.$post->id.'/media', $this->mediaInput(), $otherToken, 'stale-media-after-assignment')->assertStatus(409);
        $this->assertDatabaseCount('blog_media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('blog_automation_requests', 0);
    }

    public function test_production_issue_needs_explicit_activation_flag_and_machine_token_does_not_login_to_admin(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('blog:automation', ['action' => 'issue', 'actor' => $this->actor->id, '--name' => 'Not activated'])->assertFailed();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->app->detectEnvironment(fn () => 'testing');
        $this->withHeader('Authorization', 'Bearer '.$this->token)->getJson('/admin/blog-posts')->assertUnauthorized();
    }

    public function test_additive_migration_keeps_existing_article_id_slug_author_body_and_media(): void
    {
        $migration = require database_path('migrations/2026_10_08_000001_add_blog_automation.php');
        $migration->down();
        $post = BlogPost::create(['title' => 'Legacy article', 'excerpt' => 'Legacy summary', 'content' => '<p>Original legacy body</p>', 'author' => 'Original author', 'category' => 'Review', 'featured_image' => 'images/legacy.jpg', 'is_published' => true, 'published_at' => now()->subDay()]);
        $before = (array) DB::table('blog_posts')->find($post->id);
        $migration->up();
        $after = (array) DB::table('blog_posts')->find($post->id);
        $this->assertSame($before, array_intersect_key($after, $before));
        $this->assertNull($after['automation_actor_id']);
        $this->assertDatabaseCount('blog_automation_actors', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('blog_automation_requests', 0);
    }

    private function draft(array $changes = []): BlogPost
    {
        $post = BlogPost::create(array_replace(['title' => 'Draft article', 'excerpt' => 'Original summary', 'content' => '<p>Original body</p>', 'author' => 'Qammaris Editorial', 'category' => 'Tips', 'is_published' => false], array_diff_key($changes, ['automation_actor_id' => true, 'archived_at' => true])));
        $post->forceFill(['automation_actor_id' => $changes['automation_actor_id'] ?? $this->actor->id, 'archived_at' => $changes['archived_at'] ?? null]);
        if (array_key_exists('automation_actor_id', $changes)) {
            $post->automation_actor_id = $changes['automation_actor_id'];
        }
        $post->save();

        return $post;
    }

    private function mediaInput(): array
    {
        return ['revision' => 1, 'alt' => 'Local cover', 'license' => 'Milik Qammaris', 'crop' => 'original', 'focal_x' => 50, 'focal_y' => 50,
            'image' => UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='))];
    }

    private function api(string $method, string $path, array $data = [], ?string $token = null, ?string $key = null)
    {
        $this->app['auth']->forgetGuards();
        $headers = ['Authorization' => 'Bearer '.($token ?? $this->token), 'Accept' => 'application/json'];
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }

        return $this->json($method, self::BASE.$path, $data, $headers);
    }
}
