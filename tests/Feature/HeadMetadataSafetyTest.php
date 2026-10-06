<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadMetadataSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_child_metadata_is_escaped_in_the_global_layout(): void
    {
        $post = $this->createPublishedPost([
            'title' => 'Title </title><script>headAttack()</script>',
            'meta_description' => '"><img src=x onerror=metaAttack()>',
        ]);

        $response = $this->get(route('blog.show', $post));

        $response->assertOk();
        $response->assertDontSee('</title><script>headAttack()</script>', false);
        $response->assertDontSee('<img src=x onerror=metaAttack()>', false);
        $response->assertSee('&lt;/title&gt;&lt;script&gt;headAttack()&lt;/script&gt;', false);
        $response->assertSee('&quot;&gt;&lt;img src=x onerror=metaAttack()&gt;', false);
    }

    public function test_global_structured_data_is_valid_json_without_blade_artifacts(): void
    {
        $post = $this->createPublishedPost();
        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();

        preg_match_all(
            '/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s',
            $html,
            $matches
        );

        $this->assertCount(2, $matches[1]);

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('https://schema.org', $decoded['@context']);
            $this->assertArrayHasKey('@type', $decoded);
            $this->assertStringNotContainsString('<?php', $json);
            $this->assertStringNotContainsString('</script>', strtolower($json));
        }
    }

    private function createPublishedPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Metadata Article',
            'excerpt' => 'Article excerpt.',
            'content' => '<p>Article content.</p>',
            'author' => 'Qammaris Team',
            'category' => 'Tips',
            'is_published' => true,
            'published_at' => now()->subMinute(),
            'view_count' => 0,
            'meta_description' => 'Safe description.',
        ], $overrides));
    }
}
