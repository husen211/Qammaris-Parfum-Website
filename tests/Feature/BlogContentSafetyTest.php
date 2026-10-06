<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogContentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_article_sanitizes_legacy_rich_text_before_rendering(): void
    {
        $post = BlogPost::create($this->postPayload([
            'content' => <<<'HTML'
                <p onclick="alert(1)">Safe <strong>content</strong></p>
                <script>alert('xss')</script>
                <iframe src="https://evil.example"></iframe>
                <a href="java&#x0A;script:alert(1)" target="_blank">Unsafe link</a>
                <a href="https://qammarisparfum.id" target="_blank">Safe link</a>
                <img src="https://images.example/perfume.jpg" onerror="alert(1)" alt="Perfume">
                HTML,
        ]));

        $response = $this->get(route('blog.show', $post));

        $response->assertOk();
        $response->assertSee('<p>Safe <strong>content</strong></p>', false);
        $response->assertSee('<a target="_blank" rel="noopener noreferrer">Unsafe link</a>', false);
        $response->assertSee('href="https://qammarisparfum.id" target="_blank" rel="noopener noreferrer"', false);
        $response->assertSee('src="https://images.example/perfume.jpg" alt="Perfume"', false);
        $response->assertDontSee("alert('xss')", false);
        $response->assertDontSee('<iframe', false);
        $response->assertDontSee('evil.example', false);
        $response->assertDontSee('onclick=', false);
        $response->assertDontSee('onerror=', false);
        $response->assertDontSee('javascript:', false);
    }

    public function test_admin_store_persists_sanitized_rich_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = $this->postPayload([
            'content' => '<h2>Heading</h2><p style="color:red">Copy</p><img src="data:text/html,bad"><script>bad()</script>',
            'is_published' => '0',
            'published_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.blog-posts.store'), $payload)
            ->assertRedirect(route('admin.blog-posts.index'));

        $content = BlogPost::sole()->content;

        $this->assertStringContainsString('<h2>Heading</h2>', $content);
        $this->assertStringContainsString('<p>Copy</p>', $content);
        $this->assertStringNotContainsString('style=', $content);
        $this->assertStringNotContainsString('data:', $content);
        $this->assertStringNotContainsString('<script', $content);
    }

    private function postPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Safe Article',
            'excerpt' => 'Article excerpt.',
            'content' => '<p>Article content.</p>',
            'featured_image' => null,
            'author' => 'Qammaris Team',
            'category' => 'Tips',
            'is_published' => true,
            'published_at' => now()->subMinute(),
            'view_count' => 0,
            'meta_description' => 'Article meta description.',
        ], $overrides);
    }
}
