<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPublicationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_draft_article_cannot_be_opened_directly(): void
    {
        $post = $this->createPost([
            'is_published' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('blog.show', $post))->assertNotFound();
        $this->assertSame(0, $post->fresh()->view_count);
    }

    public function test_article_without_publish_date_cannot_be_opened_directly(): void
    {
        $post = $this->createPost(['published_at' => null]);

        $this->get(route('blog.show', $post))->assertNotFound();
        $this->assertSame(0, $post->fresh()->view_count);
    }

    public function test_scheduled_article_cannot_be_opened_before_publish_time(): void
    {
        $post = $this->createPost(['published_at' => now()->addHour()]);

        $this->get(route('blog.show', $post))->assertNotFound();
        $this->assertSame(0, $post->fresh()->view_count);
    }

    public function test_published_article_can_be_opened_and_increments_view_count(): void
    {
        $post = $this->createPost(['published_at' => now()->subMinute()]);

        $this->get(route('blog.show', $post))->assertOk();
        $this->assertSame(1, $post->fresh()->view_count);
    }

    private function createPost(array $overrides): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Publication Test',
            'excerpt' => 'Article excerpt.',
            'content' => '<p>Article content.</p>',
            'author' => 'Qammaris Team',
            'category' => 'Tips',
            'is_published' => true,
            'published_at' => now()->subMinute(),
            'view_count' => 0,
            'meta_description' => 'Article description.',
        ], $overrides));
    }
}
