<?php

namespace Tests\Feature;

use App\Actions\Blog\RecordBlogPostChange;
use App\Actions\Blog\SaveBlogMedia;
use App\Actions\Blog\SaveBlogPost;
use App\Exceptions\BlogPostConflict;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\BlogImageProcessor;
use App\Support\BlogHtmlSanitizer;
use App\Support\RenderBlogContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class JournalComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_upload_retains_original_and_generates_bounded_crop_and_responsive_sources(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD processing requires the GD runtime.');
        }
        $post = $this->article();
        $file = UploadedFile::fake()->image('bottle.png', 800, 400);
        $bytes = $file->getContent();
        $media = app(SaveBlogMedia::class)->handle($post, $this->admin(), $this->mediaData($post, ['crop' => '1:1', 'focal_x' => 100]), $file);
        $this->assertSame($bytes, Storage::disk('public')->get($media->path));
        $this->assertSame(hash('sha256', $bytes), $media->checksum);
        $this->assertSame([480, 768, 800], array_column(array_values(array_filter($media->variants, fn ($v) => $v['crop'] === 'original')), 'width'));
        foreach ($media->variants as $variant) {
            $dimensions = getimagesizefromstring(Storage::disk('public')->get($variant['path']));
            $this->assertSame($variant['width'], $dimensions[0]);
            $this->assertSame($variant['height'], $dimensions[1]);
            $this->assertLessThanOrEqual($variant['crop'] === 'original' ? 800 : 400, $variant['width']);
        }
        $this->assertStringContainsString('tidak diperbesar', $media->processing_warning);
        $this->assertSame(2, $post->fresh()->revision);
        $history = DB::table('blog_post_changes')->sole();
        $this->assertSame('media_uploaded', $history->action);
        $this->assertStringNotContainsString($media->path, $history->after);
        $this->assertStringNotContainsString('Teks alternatif lokal', $history->after);
    }

    public function test_disabled_processing_is_explicit_and_keeps_original_without_fake_variants(): void
    {
        config(['media.blog_resize' => false]);
        $post = $this->article();
        $media = app(SaveBlogMedia::class)->handle($post, $this->admin(), $this->mediaData($post, ['crop' => '16:9']), $this->image());
        $this->assertSame([], $media->variants);
        $this->assertStringContainsString('GD/WebP belum aktif', $media->processing_warning);
        Storage::disk('public')->assertExists($media->path);
        $this->actingAs($this->admin())->get(route('admin.blog-media.index', $post))->assertSee('resize dan crop belum tersedia');
    }

    public function test_derivative_write_failure_keeps_original_and_removes_partial_derivatives(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD processing requires the GD runtime.');
        }
        $file = $this->image();
        $disk = Storage::disk('public');
        $disk->put('blog/original.png', $file->getContent());
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($mock);
        try {
            app(BlogImageProcessor::class)->prepare($file, 'public', 'blog/original.png');
            $this->fail('Unverified derivative must fail.');
        } catch (RuntimeException) {
            $this->assertSame(['blog/original.png'], $disk->allFiles());
        }
    }

    public function test_upload_and_recrop_audit_failure_restore_rows_revision_and_existing_files(): void
    {
        $post = $this->article();
        $admin = $this->admin();
        $media = app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
        $files = Storage::disk('public')->allFiles();
        $before = $media->fresh()->getAttributes();
        $audit = Mockery::mock(RecordBlogPostChange::class)->makePartial();
        $audit->shouldReceive('handle')->twice()->andThrow(new RuntimeException('Database audit failed'));
        $this->app->instance(RecordBlogPostChange::class, $audit);
        foreach ([false, true] as $recrop) {
            try {
                app(SaveBlogMedia::class)->handle($post->fresh(), $admin, $this->mediaData($post->fresh(), ['crop' => '1:1']), $recrop ? null : $this->image(), $recrop ? $media->id : null);
                $this->fail('Audit failure must fail mutation.');
            } catch (RuntimeException) {
                $this->assertSame($files, Storage::disk('public')->allFiles());
                $this->assertSame($before, $media->fresh()->getAttributes());
                $this->assertSame(2, $post->fresh()->revision);
                $this->assertDatabaseCount('blog_media', 1);
                $this->assertDatabaseCount('blog_post_changes', 1);
            }
        }
    }

    public function test_stale_media_update_cannot_attach_an_upload_and_noop_does_not_audit(): void
    {
        $post = $this->article();
        $admin = $this->admin();
        $media = app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
        $files = Storage::disk('public')->allFiles();
        app(SaveBlogMedia::class)->handle($post->fresh(), $admin, $this->mediaData($post->fresh()), mediaId: $media->id);
        $this->assertSame(2, $post->fresh()->revision);
        $this->assertDatabaseCount('blog_post_changes', 1);
        try {
            app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
            $this->fail('Stale revision must fail.');
        } catch (BlogPostConflict) {
            $this->assertSame($files, Storage::disk('public')->allFiles());
        }
    }

    public function test_media_ownership_authorization_validation_and_archive_are_enforced(): void
    {
        $post = $this->article();
        $other = $this->article(['title' => 'Other']);
        $admin = $this->admin();
        $media = app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
        $this->actingAs($admin)->patchJson(route('admin.blog-media.update', [$other, $media->id]), $this->mediaData($other))->assertNotFound();
        $this->postJson(route('admin.blog-media.store', $post), $this->mediaData($post->fresh(), ['crop' => 'free', 'source_url' => 'javascript:alert(1)']) + ['image' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors(['crop', 'source_url']);
        $this->actingAs(User::factory()->create(['role' => 'customer']))->get(route('admin.blog-media.index', $post))->assertForbidden();
        $this->postJson(route('admin.blog-media.store', $post), $this->mediaData($post->fresh()) + ['image' => $this->image()])->assertForbidden();
        $post->forceFill(['featured_media_id' => $media->id])->save();
        $this->actingAs($admin)->patchJson(route('admin.blog-media.archive', [$post, $media->id]), ['revision' => 2])->assertUnprocessable();
        $post->forceFill(['featured_media_id' => null, 'content' => '<div data-qammaris-media="'.$media->id.'"></div>'])->save();
        $this->get(route('blog.show', $post))->assertSee('Teks alternatif lokal');
        $this->patch(route('admin.blog-media.archive', [$post, $media->id]), ['revision' => 2])->assertRedirect();
        $this->get(route('blog.show', $post))->assertDontSee('Teks alternatif lokal');
        Storage::disk('public')->assertExists($media->path);
        $this->assertNotNull($media->fresh()->archived_at);
    }

    public function test_gallery_and_featured_selection_reject_foreign_media_on_save_and_hide_on_read(): void
    {
        $post = $this->article();
        $other = $this->article(['title' => 'Other']);
        $admin = $this->admin();
        $foreign = app(SaveBlogMedia::class)->handle($other, $admin, $this->mediaData($other), $this->image());
        $data = $this->postData($post);
        foreach ([['featured_media_id' => $foreign->id], ['content' => '<div data-qammaris-media="'.$foreign->id.'"></div>']] as $invalid) {
            $this->actingAs($admin)->putJson(route('admin.blog-posts.update', $post), array_replace($data, $invalid))->assertUnprocessable();
        }
        $post->update(['content' => '<div data-qammaris-media="'.$foreign->id.'"></div>']);
        $this->get(route('blog.show', $post))->assertDontSee('Teks alternatif lokal');
        $this->assertSame(1, $post->fresh()->revision);
    }

    public function test_structured_lists_preserve_order_clear_explicitly_and_validate_dangerous_input(): void
    {
        $post = $this->article();
        $one = $this->article(['title' => 'First']);
        $two = $this->article(['title' => 'Second']);
        $admin = $this->admin();
        $data = $this->postData($post) + ['related_article_ids' => [$two->id, $one->id], 'faqs' => [['question' => 'Apa?', 'answer' => '<script>tetap teks</script>']], 'references' => [['title' => 'Sumber', 'url' => 'https://example.org/reference']]];
        $this->actingAs($admin)->put(route('admin.blog-posts.update', $post), $data)->assertRedirect();
        $this->assertSame([$two->id, $one->id], $post->fresh()->related_article_ids);
        $this->get(route('blog.show', $post))->assertSeeInOrder(['Second', 'First'])->assertSee('&lt;script&gt;tetap teks&lt;/script&gt;', false);
        $data['revision'] = 2;
        app(SaveBlogPost::class)->handle($data, $admin, $post);
        $this->assertSame(2, $post->fresh()->revision);
        $this->assertDatabaseCount('blog_post_changes', 1);
        $this->putJson(route('admin.blog-posts.update', $post), array_replace($data, ['references' => [['title' => 'Bad', 'url' => 'javascript:alert(1)']]]))->assertUnprocessable();
        $this->putJson(route('admin.blog-posts.update', $post), array_replace($data, ['related_article_ids' => [$post->id]]))->assertUnprocessable();
        $this->putJson(route('admin.blog-posts.update', $post), array_replace($data, ['related_article_ids' => [(string) $post->id]]))->assertUnprocessable();
        $this->put(route('admin.blog-posts.update', $post), $this->postData($post->fresh()) + ['components_present' => 1])->assertRedirect();
        $this->assertSame([], $post->fresh()->related_article_ids);
        $this->assertSame([], $post->fresh()->faqs);
        $this->assertSame([], $post->fresh()->references);
    }

    public function test_components_discard_forged_markup_and_render_only_fixed_safe_youtube_and_text(): void
    {
        $source = '<div data-qammaris-youtube="dQw4w9WgXcQ" onclick="bad()"><iframe src="https://evil.test"></iframe></div>'.
            '<div data-qammaris-callout="tip" data-title="&lt;img onerror=bad()&gt;" data-text="Aroma &amp; catatan"><script>bad()</script></div>'.
            '<div data-qammaris-cta="javascript:alert(1)" data-label="Bad">Forged CTA</div><iframe src="https://evil.test"></iframe>'.
            '<div data-qammaris-cta="/products" data-label="Lihat katalog"></div>';
        $safe = app(BlogHtmlSanitizer::class)->sanitize($source);
        $this->assertStringNotContainsString('iframe', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('Forged CTA', $safe);
        $this->assertStringContainsString('data-qammaris-youtube="dQw4w9WgXcQ"', $safe);
        $html = app(RenderBlogContent::class)->document($safe)['html'];
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringContainsString('&lt;img onerror=bad()&gt;', $html);
        $this->assertStringContainsString('Lihat katalog', $html);
    }

    public function test_live_product_cards_track_price_stock_hidden_and_order_without_catalog_mutation(): void
    {
        $brand = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $category = Category::create(['name' => 'Eau de Parfum', 'is_active' => true]);
        $one = Product::create(['name' => 'Produk One', 'brand_id' => $brand->id, 'category_id' => $category->id, 'is_active' => true, 'publication_status' => 'published', 'availability_status' => 'sold_out', 'availability_source' => 'qammaris_app']);
        $two = Product::create(['name' => 'Produk Two', 'brand_id' => $brand->id, 'category_id' => $category->id, 'is_active' => true, 'publication_status' => 'published']);
        $offer = $one->variants()->create(['volume' => 100, 'price' => 450000, 'is_active' => true]);
        $post = $this->article(['content' => '<div data-qammaris-product="'.$one->id.'"><p>Rp 1 stok 999</p></div>', 'related_product_ids' => [$two->id, $one->id]]);
        $before = $one->fresh()->getAttributes();
        $this->get(route('blog.show', $post))->assertSee('Rp 450.000')->assertSee('Habis')->assertDontSee('stok 999')->assertViewHas('linkedProducts', fn ($items) => $items->pluck('id')->all() === [$two->id, $one->id]);
        $this->assertSame($before, $one->fresh()->getAttributes());
        $offer->update(['price' => 479000]);
        $one->forceFill(['availability_status' => 'available', 'availability_source' => 'qammaris_app'])->save();
        $this->get(route('blog.show', $post))->assertSee('Rp 479.000')->assertSee('Tersedia')->assertDontSee('Rp 450.000');
        $one->forceFill(['qammaris_app_hidden' => true])->save();
        $this->get(route('blog.show', $post))->assertDontSee('Produk One');
        $this->get('/blog?search=Produk+Two')->assertViewHas('posts', fn ($items) => $items->total() === 1);
    }

    public function test_preview_uses_owned_gallery_and_components_without_mutation_or_views(): void
    {
        $post = $this->article();
        $admin = $this->admin();
        $one = app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
        $two = app(SaveBlogMedia::class)->handle($post->fresh(), $admin, $this->mediaData($post->fresh(), ['alt' => 'Foto kedua']), $this->image());
        $files = Storage::disk('public')->allFiles();
        $data = $this->postData($post->fresh());
        $data['content'] = '<p>Preview artikel</p><div data-qammaris-gallery="'.$one->id.','.$two->id.'"></div>';
        $this->actingAs($admin)->post(route('admin.blog-posts.preview-existing', $post), $data)->assertOk()->assertSee('Foto kedua')->assertSee('journal-gallery')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(0, $post->fresh()->view_count);
        $this->assertSame(3, $post->fresh()->revision);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_original_checksum_and_memory_limits_fail_before_generating_derivatives(): void
    {
        $file = $this->image();
        Storage::disk('public')->put('blog/original.png', 'wrong bytes');
        try {
            app(BlogImageProcessor::class)->prepare($file, 'public', 'blog/original.png');
            $this->fail('Changed original must be rejected.');
        } catch (RuntimeException) {
            $this->assertSame(['blog/original.png'], Storage::disk('public')->allFiles());
        }
        if (! extension_loaded('gd')) {
            return;
        }
        $budget = ini_get('memory_limit');
        try {
            ini_set('memory_limit', (string) (memory_get_usage(true) + 1024 * 1024));
            try {
                app(BlogImageProcessor::class)->prepareBytes($file->getContent(), 'public', 'blog/original.png');
                $this->fail('Insufficient processing memory must be rejected.');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('kapasitas pemrosesan', $error->errors()['image'][0]);
                $this->assertSame(['blog/original.png'], Storage::disk('public')->allFiles());
            }
        } finally {
            ini_set('memory_limit', $budget);
        }
    }

    public function test_successful_recrop_retains_original_and_previous_generation_and_syncs_featured_alt(): void
    {
        $post = $this->article();
        $admin = $this->admin();
        $media = app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
        $oldFiles = Storage::disk('public')->allFiles();
        $post = app(SaveBlogPost::class)->handle($this->postData($post->fresh()) + ['featured_media_id' => $media->id], $admin, $post);
        $updated = app(SaveBlogMedia::class)->handle($post->fresh(), $admin, $this->mediaData($post->fresh(), ['crop' => '1:1', 'alt' => 'Alt baru']), mediaId: $media->id);
        foreach ($oldFiles as $file) {
            Storage::disk('public')->assertExists($file);
        }
        $this->assertSame($media->checksum, $updated->checksum);
        $this->assertSame($media->path, $updated->path);
        $this->assertSame('Alt baru', $post->fresh()->featured_image_alt);
        $post = app(SaveBlogPost::class)->handle($this->postData($post->fresh()) + ['featured_image_alt' => 'Alt editor'], $admin, $post);
        $this->assertSame('Alt editor', $media->fresh()->alt);
        $this->assertSame($media->id, $post->fresh()->hero_media->id);
        Storage::disk('public')->put($media->path, 'changed source');
        try {
            app(SaveBlogMedia::class)->handle($post->fresh(), $admin, $this->mediaData($post->fresh(), ['crop' => '4:3']), mediaId: $media->id);
            $this->fail('Changed source cannot be recropped.');
        } catch (RuntimeException) {
            $this->assertSame('1:1', $media->fresh()->crop);
        }
    }

    public function test_media_failure_retains_only_failed_form_input_and_conflict_requires_reload(): void
    {
        $post = $this->article();
        $admin = $this->admin();
        $one = app(SaveBlogMedia::class)->handle($post, $admin, $this->mediaData($post), $this->image());
        $two = app(SaveBlogMedia::class)->handle($post->fresh(), $admin, $this->mediaData($post->fresh(), ['alt' => 'Foto kedua']), $this->image());
        $url = route('admin.blog-media.index', $post);
        $stale = $this->mediaData($post->fresh(), ['revision' => 1, 'alt' => 'Isian belum tersimpan']) + ['media_id' => $one->id];
        $this->actingAs($admin)->from($url)->patch(route('admin.blog-media.update', [$post, $one->id]), $stale)->assertRedirect($url)->assertSessionHasErrors('revision');
        $this->get($url)->assertSee('Muat ulang media terbaru')->assertSee('Isian belum tersimpan')->assertSee('Foto kedua');
        $this->assertSame('Teks alternatif lokal', $one->fresh()->alt);
        $audit = Mockery::mock(RecordBlogPostChange::class)->makePartial();
        $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('Synthetic DB failure'));
        $this->app->instance(RecordBlogPostChange::class, $audit);
        $data = $this->mediaData($post->fresh(), ['alt' => 'Coba lagi']) + ['media_id' => $two->id];
        $this->from($url)->patch(route('admin.blog-media.update', [$post, $two->id]), $data)->assertRedirect($url)->assertSessionHasErrors('image');
        $this->get($url)->assertSee('Coba lagi')->assertSee('Teks alternatif lokal');
        $this->assertSame('Foto kedua', $two->fresh()->alt);
    }

    public function test_additive_media_migration_preserves_legacy_article_and_can_replay_on_empty_media(): void
    {
        $migration = require database_path('migrations/2026_10_07_000005_add_journal_media_and_relations.php');
        $migration->down();
        $post = $this->article(['slug' => 'legacy-stable', 'author' => 'Penulis asli', 'featured_image' => 'images/blog_images/review-afnan.jpg', 'view_count' => 42]);
        $before = (array) DB::table('blog_posts')->find($post->id);
        $migration->up();
        $after = (array) DB::table('blog_posts')->find($post->id);
        $this->assertSame($before, array_intersect_key($after, $before));
        $this->assertNull($after['featured_media_id']);
        $this->assertNull($after['related_product_ids']);
        $this->assertDatabaseCount('blog_media', 0);
        $migration->down();
        $migration->up();
        $this->assertSame($before, array_intersect_key((array) DB::table('blog_posts')->find($post->id), $before));
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    public function test_empty_editor_lists_do_not_turn_a_legacy_noop_into_an_editorial_change(): void
    {
        $post = $this->article();
        $data = $this->postData($post) + ['components_present' => 1];
        $this->actingAs($this->admin())->put(route('admin.blog-posts.update', $post), $data)->assertRedirect(route('admin.blog-posts.index'));
        $this->assertSame(1, $post->fresh()->revision);
        foreach (['related_product_ids', 'related_article_ids', 'faqs', 'references'] as $field) {
            $this->assertNull($post->fresh()->{$field});
        }
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_replace(['title' => 'Artikel lokal', 'content' => '<p>Teks bermakna.</p>', 'excerpt' => 'Ringkasan', 'author' => 'Editorial', 'category' => 'Tips', 'is_published' => true, 'published_at' => now()->subDay()], $overrides))->fresh();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function mediaData(BlogPost $post, array $overrides = []): array
    {
        return array_replace(['revision' => $post->revision, 'alt' => 'Teks alternatif lokal', 'caption' => 'Caption lokal', 'credit' => 'Owner', 'license' => 'Milik Qammaris', 'source_url' => null, 'crop' => 'original', 'focal_x' => 50, 'focal_y' => 50], $overrides);
    }

    private function postData(BlogPost $post): array
    {
        return $post->only(['title', 'excerpt', 'content', 'category', 'author', 'is_published']) + ['revision' => $post->revision];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('local.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }
}
