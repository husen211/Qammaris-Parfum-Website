<?php

namespace Tests\Feature;

use App\Actions\Blog\SaveBlogPost;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\JournalMetadata;
use App\Support\RenderBlogContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JournalPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_landing_featured_filters_and_pagination_never_expose_private_articles(): void
    {
        $featured = $this->article(['title' => 'Pilihan utama', 'is_featured' => true]);
        for ($i = 0; $i < 11; $i++) {
            $this->article(['title' => 'Artikel '.$i, 'category' => 'Review']);
        }
        $this->article(['title' => 'Draft tersembunyi', 'is_published' => false, 'is_featured' => true]);
        $this->article(['title' => 'Jadwal tersembunyi', 'published_at' => now()->addDay()]);
        $this->article(['title' => 'Arsip tersembunyi'])->forceFill(['archived_at' => now()])->save();
        $this->get('/blog')->assertOk()->assertViewHas('featured', fn ($post) => $post->id === $featured->id)
            ->assertViewHas('posts', fn ($posts) => $posts->total() === 12)
            ->assertDontSee('Draft tersembunyi')->assertDontSee('Jadwal tersembunyi')->assertDontSee('Arsip tersembunyi');
        $this->get('/blog?page=2')->assertOk()->assertViewHas('featured', null)
            ->assertSee('rel="canonical" href="'.route('blog.index').'?page=2"', false);
        $this->get('/blog/category/review?search=artikel')->assertOk()->assertViewHas('posts', fn ($posts) => $posts->total() === 11)
            ->assertSee('search=artikel', false)->assertViewHas('featured', null);
        $this->get('/blog?search[]=bad&category[]=bad')->assertOk()->assertViewHas('search', '');
    }

    public function test_search_prioritizes_metadata_and_keeps_body_typo_strict(): void
    {
        $target = $this->article(['title' => 'Reverie Aqua', 'excerpt' => 'Ulasan aroma']);
        $body = $this->article(['title' => 'Catatan perjalanan', 'content' => '<p>Reverie Aqua dibahas sebagai pembanding.</p>']);
        $this->article(['title' => 'CHNO', 'content' => '<p>Reveria untuk aktivitas.</p>']);
        $this->article(['title' => 'Reverie rahasia', 'is_published' => false]);
        $this->get('/blog?search=rverie')->assertOk()->assertViewHas('posts', fn ($posts) => $posts->pluck('id')->all() === [$target->id]);
        $this->get('/blog?search=reverie')->assertOk()->assertViewHas('posts', fn ($posts) => $posts->pluck('id')->all() === [$target->id, $body->id]);
        $this->get('/blog?search=zzz')->assertSee('Artikel tidak ditemukan')->assertDontSee('Reverie Aqua');
        $this->get('/blog?search=!!!')->assertViewHas('posts', fn ($posts) => $posts->total() === 0);
        $this->article(['title' => 'Batas paragraf', 'content' => '<p>Awal</p><p>Reverie Aqua</p><p>Akhir</p>']);
        $this->get('/blog?search=reverie+aqua')->assertViewHas('posts', fn ($posts) => $posts->total() === 3);
    }

    public function test_tags_and_eligible_product_brand_are_search_metadata_without_catalog_writes(): void
    {
        $tag = BlogTag::create(['name' => 'Segar', 'slug' => 'segar', 'is_active' => true]);
        $post = $this->article(['title' => 'Cerita produk']);
        $post->tags()->attach($tag);
        $this->get('/blog?search=segar')->assertViewHas('posts', fn ($posts) => $posts->total() === 1);
        $tag->update(['is_active' => false]);
        $this->get('/blog?search=segar')->assertViewHas('posts', fn ($posts) => $posts->total() === 0);
        $product = Product::create(['name' => 'Pilihan botol', 'publication_status' => 'published', 'is_active' => true,
            'brand_id' => Brand::create(['name' => 'Afnan', 'is_active' => true])->id,
            'category_id' => Category::create(['name' => 'Eau de Parfum', 'is_active' => true])->id]);
        $post->update(['content' => '<div data-qammaris-product="'.$product->id.'"></div>']);
        $before = $product->fresh()->getAttributes();
        $this->get('/blog?search=afnan')->assertViewHas('posts', fn ($posts) => $posts->total() === 1);
        $this->article(['title' => 'Penanda palsu', 'content' => '<script data-qammaris-product="'.$product->id.'"></script><span data-qammaris-product="'.$product->id.'"></span>']);
        $this->get('/blog?search=afnan')->assertViewHas('posts', fn ($posts) => $posts->total() === 1);
        $this->get(route('blog.show', $post))->assertSee('Produk dalam artikel')->assertSee('Pilihan botol');
        $this->assertSame($before, $product->fresh()->getAttributes());
        $product->forceFill(['qammaris_app_hidden' => true])->save();
        $this->get('/blog?search=afnan')->assertViewHas('posts', fn ($posts) => $posts->total() === 0);
        $this->get(route('blog.show', $post))->assertDontSee('Pilihan botol');
    }

    public function test_toc_has_unique_safe_anchors_and_tables_are_contained_without_rewriting_source(): void
    {
        $source = '<h2 id="bad">Sama</h2><h3>Bagian</h3><h2>Sama</h2><h2>Sama</h2><table><tr><td colspan="2">Isi</td></tr></table><script>unsafe()</script>';
        $post = $this->article(['content' => $source]);
        $response = $this->get(route('blog.show', $post))->assertOk()->assertSee('Daftar isi')->assertDontSee('unsafe()', false);
        $response->assertSee('href="#journal-section-1"', false)->assertSee('id="journal-section-4"', false)
            ->assertSee('class="journal-table" role="region" aria-label="Tabel artikel" tabindex="0"', false);
        $this->assertSame($source, $post->fresh()->content);
        $this->assertCount(0, app(RenderBlogContent::class)->document('<h2>Satu</h2><h2>Dua</h2>')['toc']);
    }

    public function test_seo_metadata_schema_and_clean_share_urls_use_visible_article_facts(): void
    {
        $post = $this->article(['title' => 'Judul </script><svg onload=bad()>', 'author' => 'Penulis asli', 'subtitle' => 'Pengantar terpisah',
            'seo_title' => 'Judul SEO', 'og_title' => 'Judul sosial', 'og_description' => 'Ringkasan sosial']);
        $post->forceFill(['content_updated_at' => now()->subMinute()])->save();
        $response = $this->get(route('blog.show', $post).'?utm_source=test')->assertOk();
        $response->assertSee('<link rel="canonical" href="'.route('blog.show', $post).'">', false)
            ->assertSee('<meta property="og:title" content="Judul sosial">', false)
            ->assertSee('<meta property="og:image" content="'.$post->featured_image_url.'">', false)
            ->assertSee('Pengantar terpisah')->assertDontSee(' views')->assertDontSee('Share this article');
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $schemas = array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
        $article = collect($schemas)->firstWhere('@type', 'BlogPosting');
        $this->assertSame($post->title, $article['headline']);
        $this->assertSame('Penulis asli', $article['author']['name']);
        $this->assertSame($post->content_updated_at->toAtomString(), $article['dateModified']);
        $this->assertSame($post->featured_image_url, $article['image']);
        $this->assertCount(3, collect($schemas)->firstWhere('@type', 'BreadcrumbList')['itemListElement']);
        $response->assertDontSee('<svg onload=bad()>', false)->assertDontSee('utm_source=test', false);
    }

    public function test_https_overrides_require_explicit_valid_input_and_audit_hashes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['http://example.test/a', 'javascript:alert(1)', 'https://user:pass@example.test/a', 'https://example.test/a#part', ['https://example.test/a']] as $invalid) {
            $this->actingAs($admin)->postJson(route('admin.blog-posts.store'), ['canonical_url' => $invalid, 'og_image_url' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors(['canonical_url', 'og_image_url']);
        }
        $post = $this->article();
        app(SaveBlogPost::class)->handle(['revision' => 1, 'title' => $post->title, 'excerpt' => $post->excerpt, 'content' => $post->content,
            'category' => 'Tips', 'is_published' => true, 'canonical_url' => 'https://example.test/original', 'og_image_url' => 'https://example.test/owner.jpg',
            'seo_indexable' => false, 'seo_followable' => false], $admin, $post);
        $this->get(route('blog.show', $post))->assertSee('content="noindex,nofollow"', false)->assertSee('href="https://example.test/original"', false)
            ->assertSee('content="https://example.test/owner.jpg"', false);
        $audit = DB::table('blog_post_changes')->sole();
        $this->assertStringContainsString('canonical_hash', $audit->after);
        $this->assertStringNotContainsString('https://example.test', $audit->after);
        $this->assertFalse(JournalMetadata::selfCanonical($post->fresh()));
    }

    public function test_sitemap_uses_editorial_time_excludes_nonindexable_and_expires_at_schedule_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        cache()->forget('sitemap.xml');
        $public = $this->article();
        $public->forceFill(['content_updated_at' => now()->subHour()])->save();
        $this->article(['title' => 'Nonindex', 'seo_indexable' => false]);
        $this->article(['title' => 'Canonical elsewhere', 'canonical_url' => 'https://example.test/article']);
        $scheduled = $this->article(['title' => 'Jadwal', 'published_at' => now()->addSeconds(20)]);
        $first = $this->get('/sitemap.xml')->assertOk()->assertSee($public->content_updated_at->toAtomString(), false)
            ->assertDontSee('/blog/nonindex', false)->assertDontSee('/blog/canonical-elsewhere', false)->assertDontSee(route('blog.show', $scheduled), false);
        $this->assertStringContainsString('max-age=20', $first->headers->get('Cache-Control'));
        $public->increment('view_count');
        $this->travel(10)->seconds();
        $this->get('/sitemap.xml')->assertSee($public->content_updated_at->toAtomString(), false)->assertHeader('Cache-Control', 'max-age=10, public');
        $this->travel(10)->seconds();
        $this->get('/sitemap.xml')->assertSee(route('blog.show', $scheduled), false);
        $this->travelBack();
    }

    public function test_preview_renders_toc_without_article_schema_count_or_share(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = $this->article();
        $before = $post->fresh()->getAttributes();
        $this->actingAs($admin)->post(route('admin.blog-posts.preview-existing', $post), ['title' => 'Preview', 'category' => 'Tips',
            'content' => '<h2>A</h2><h2>B</h2><h2>C</h2>'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Daftar isi')->assertDontSee('BlogPosting')->assertDontSee('Bagikan artikel');
        $this->assertSame($before, $post->fresh()->getAttributes());
        $this->assertDatabaseCount('blog_post_changes', 0);
    }

    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_replace(['title' => 'Artikel biasa', 'excerpt' => 'Ringkasan netral', 'content' => '<p>Isi artikel netral.</p>',
            'category' => 'Tips', 'author' => 'Qammaris Editorial', 'featured_image' => 'images/blog_images/review-afnan.jpg',
            'featured_image_alt' => 'Foto parfum', 'is_published' => true, 'published_at' => now()->subDay()], $overrides));
    }
}
