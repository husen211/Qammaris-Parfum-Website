<?php

namespace Tests\Feature;

use App\Actions\Blog\RecordBlogPostChange;
use App\Actions\Blog\SaveBlogPost;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\BlogHtmlSanitizer;
use App\Support\RenderBlogContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BlogEditorialCmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_draft_has_author_and_never_becomes_public(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.blog-posts.store'), [])->assertRedirect();
        $post = BlogPost::firstOrFail();
        $this->assertSame('Qammaris Editorial', $post->author);
        $this->assertSame('', $post->content);
        $this->assertSame('1 menit', $post->reading_time);
        $this->get(route('blog.show', $post))->assertNotFound();
    }

    public function test_new_publish_requires_meaningful_content_image_and_alt_and_compensates_upload(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->postJson(route('admin.blog-posts.store'), $this->data(['is_published' => true, 'content' => '<script>alert(1)</script>']))
            ->assertUnprocessable()->assertJsonValidationErrors(['content', 'featured_image', 'featured_image_alt']);
        $this->postJson(route('admin.blog-posts.store'), $this->data(['is_published' => true]) + ['featured_image' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors('featured_image_alt');
        $this->assertDatabaseCount('blog_posts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_complete_scheduled_article_is_private_until_its_time(): void
    {
        Storage::fake('public');
        $date = now()->addDay();
        $this->actingAs($this->admin())->postJson(route('admin.blog-posts.store'),
            $this->data(['is_published' => true, 'published_at' => $date->toAtomString(), 'featured_image_alt' => 'Botol parfum']) + ['featured_image' => $this->image()])->assertRedirect();
        $post = BlogPost::firstOrFail();
        $this->get(route('blog.show', $post))->assertNotFound();
        $this->travelTo($date->addMinute());
        $this->get(route('blog.show', $post))->assertOk()->assertSee('alt="Botol parfum"', false);
    }

    public function test_published_legacy_article_can_be_edited_without_new_image_metadata(): void
    {
        $post = BlogPost::create($this->data(['is_published' => true, 'published_at' => now()->subDay(), 'author' => 'Penulis asli']));
        $slug = $post->slug;
        $this->actingAs($this->admin())->putJson(route('admin.blog-posts.update', $post), $this->data(['revision' => 1, 'title' => 'Judul diperbarui', 'is_published' => true, 'author' => null]))->assertRedirect();
        $this->assertSame($slug, $post->fresh()->slug);
        $this->assertSame('Penulis asli', $post->fresh()->author);
        $this->assertNull($post->fresh()->featured_image_alt);
        $this->get(route('blog.show', $post))->assertOk();
    }

    public function test_taxonomy_preserves_legacy_urls_and_adds_new_categories_without_enum_rewrite(): void
    {
        $actor = $this->admin();
        $this->assertSame(['Tips', 'Review', 'Panduan', 'Berita'], BlogCategory::orderBy('id')->pluck('name')->all());
        $this->actingAs($actor)->post(route('admin.blog-taxonomy.store'), ['kind' => 'category', 'name' => 'Cerita Aroma'])->assertRedirect();
        $post = app(SaveBlogPost::class)->handle($this->data(['category' => 'Cerita Aroma']), $actor);
        $this->assertSame('Cerita Aroma', $post->fresh()->category);
        $this->assertSame('Tips', $post->fresh()->getRawOriginal('category'));
        $post->update(['is_published' => true, 'published_at' => now()->subDay()]);
        $this->get(route('blog.category', 'cerita-aroma'))->assertOk()->assertSee($post->title);
        $this->get(route('blog.index'))->assertSee('Cerita Aroma');
        foreach (['tips', 'review', 'panduan', 'berita'] as $category) {
            $this->get(route('blog.category', $category))->assertOk();
        }
    }

    public function test_inactive_category_and_new_inactive_tag_cannot_be_published_or_added(): void
    {
        $actor = $this->admin();
        BlogCategory::where('name', 'Tips')->update(['is_active' => false]);
        $tag = BlogTag::create(['name' => 'Lama', 'slug' => 'lama', 'is_active' => false]);
        $this->actingAs($actor)->postJson(route('admin.blog-posts.store'), $this->data(['is_published' => true]))->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson(route('admin.blog-posts.store'), $this->data(['tag_ids' => [$tag->id]]))->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->assertDatabaseCount('blog_posts', 0);
    }

    public function test_tag_changes_are_revisioned_audited_and_transactional_and_reordering_is_noop(): void
    {
        $actor = $this->admin();
        $tag = BlogTag::create(['name' => 'Segar', 'slug' => 'segar', 'is_active' => true]);
        $post = app(SaveBlogPost::class)->handle($this->data(['tag_ids' => [$tag->id]]), $actor);
        $time = $post->content_updated_at;
        app(SaveBlogPost::class)->handle($this->data(['tag_ids' => [(string) $tag->id], 'revision' => 1]), $actor, $post);
        $this->assertSame(1, $post->fresh()->revision);
        $this->assertEquals($time, $post->fresh()->content_updated_at);
        $this->assertDatabaseCount('blog_post_changes', 1);
        $this->mock(RecordBlogPostChange::class, fn ($mock) => $mock->shouldReceive('snapshot')->andReturn([])->getMock()->shouldReceive('handle')->andThrow(new RuntimeException('Unavailable')));
        $this->actingAs($actor)->putJson(route('admin.blog-posts.update', $post), $this->data(['tag_ids' => [], 'revision' => 1]))->assertStatus(500);
        $this->assertSame([$tag->id], $post->fresh()->tags->pluck('id')->all());
        $this->assertSame(1, $post->fresh()->revision);
    }

    public function test_clear_all_tags_is_explicit_and_audits_no_body_text(): void
    {
        $actor = $this->admin();
        $tag = BlogTag::create(['name' => 'Segar', 'slug' => 'segar', 'is_active' => true]);
        $post = app(SaveBlogPost::class)->handle($this->data(['tag_ids' => [$tag->id]]), $actor);
        $this->actingAs($actor)->putJson(route('admin.blog-posts.update', $post), $this->data(['tags_present' => 1, 'revision' => 1]))->assertRedirect();
        $this->assertCount(0, $post->fresh()->tags);
        $audit = DB::table('blog_post_changes')->orderByDesc('id')->first();
        $this->assertContains('tag_ids', json_decode($audit->changed_fields, true));
        $this->assertStringNotContainsString('Pilih parfum yang nyaman', $audit->after);
    }

    public function test_preview_is_authenticated_noindex_no_store_and_never_mutates_article_or_files(): void
    {
        Storage::fake('public');
        $post = BlogPost::create($this->data());
        $before = $post->fresh()->getAttributes();
        $this->post(route('admin.blog-posts.preview-existing', $post), [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'customer']))->post(route('admin.blog-posts.preview'), [])->assertForbidden();
        $response = $this->actingAs($this->admin())->post(route('admin.blog-posts.preview-existing', $post), $this->data(['title' => 'Preview baru', 'subtitle' => 'Pengantar preview', 'seo_title' => 'SEO preview']) + ['featured_image' => $this->image()]);
        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Preview baru')->assertSee('Pengantar preview')->assertSee('SEO preview');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $post->fresh()->getAttributes());
        $this->assertDatabaseCount('blog_post_changes', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_product_markers_ignore_injected_content_and_resolve_only_public_current_products(): void
    {
        $product = Product::create(['name' => 'Produk asli', 'publication_status' => 'published', 'is_active' => true, 'qammaris_app_hidden' => false,
            'brand_id' => Brand::create(['name' => 'Merek uji', 'is_active' => true])->id,
            'category_id' => Category::create(['name' => 'Eau de Parfum', 'is_active' => true])->id]);
        $html = '<div data-qammaris-product="'.$product->id.'" onclick="bad()">Harga palsu<script>bad()</script></div>';
        $rendered = app(RenderBlogContent::class)->handle($html);
        $this->assertStringContainsString('Produk asli', $rendered);
        $this->assertStringNotContainsString('Harga palsu', $rendered);
        $product->forceFill(['name' => 'Nama baru', 'qammaris_app_hidden' => true])->save();
        $this->assertSame('', app(RenderBlogContent::class)->handle($html));
        $product->forceFill(['qammaris_app_hidden' => false])->save();
        $this->assertStringContainsString('Nama baru', app(RenderBlogContent::class)->handle($html));
        $this->actingAs($this->admin())->postJson(route('admin.blog-posts.store'), $this->data(['content' => '<div data-qammaris-product="99999999"></div>']))->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_merged_table_cells_survive_sanitizer_but_style_scripts_and_invalid_spans_do_not(): void
    {
        $safe = app(BlogHtmlSanitizer::class)->sanitize('<table style="bad"><tr><th colspan="2">A</th><td rowspan="999" onclick="bad()">B</td></tr></table><iframe src="bad"></iframe>');
        $this->assertStringContainsString('colspan="2"', $safe);
        $this->assertStringNotContainsString('rowspan', $safe);
        $this->assertStringNotContainsString('style', $safe);
        $this->assertStringNotContainsString('onclick', $safe);
        $this->assertStringNotContainsString('iframe', $safe);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_cms_migration_leaves_original_article_columns_unchanged(): void
    {
        $migration = require database_path('migrations/2026_10_07_000003_add_blog_editorial_cms.php');
        $migration->down();
        $id = DB::table('blog_posts')->insertGetId($this->data([
            'slug' => 'legacy-stable', 'author' => 'Penulis asli', 'featured_image' => 'images/blog_images/review-afnan.jpg',
            'is_published' => true, 'published_at' => now()->subDay(), 'view_count' => 42,
        ]));
        $before = (array) DB::table('blog_posts')->find($id);
        $migration->up();
        $after = (array) DB::table('blog_posts')->find($id);
        $this->assertSame($before, array_intersect_key($after, $before));
        $this->assertNull($after['category_id']);
        $this->assertNull($after['featured_image_alt']);
        $this->assertDatabaseCount('blog_post_tag', 0);
        $this->assertDatabaseCount('blog_post_changes', 0);
        $this->assertDatabaseCount('blog_categories', 4);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['title' => 'Artikel parfum', 'excerpt' => 'Ringkasan parfum harian', 'content' => '<p>Pilih parfum yang nyaman digunakan sehari-hari.</p>', 'category' => 'Tips', 'is_published' => false], $overrides);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }
}
