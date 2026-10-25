<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO-04: old catalog URLs that Google still requests move permanently to the same, still published product. */
class SeoOldProductUrlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['product_redirects' => ['rasasi-hawas-for-him' => 'hawas-for-him-men-100-ml', 'old-hidden' => 'hidden-now']]);
    }

    public function test_a_mapped_old_url_moves_permanently_to_the_published_product(): void
    {
        $this->product('Hawas For Him (Men) 100 Ml', 'hawas-for-him-men-100-ml');

        $this->get('/products/rasasi-hawas-for-him?utm_source=google')
            ->assertStatus(301)->assertRedirect(route('products.show', 'hawas-for-him-men-100-ml'));
        $this->get(route('products.show', 'hawas-for-him-men-100-ml'))->assertOk();
    }

    public function test_unknown_slugs_and_unpublished_targets_stay_not_found(): void
    {
        $this->product('Hidden Now', 'hidden-now', Product::PUBLICATION_DRAFT);

        $this->get('/products/never-existed')->assertNotFound();
        $this->get('/products/old-hidden')->assertNotFound();
    }

    public function test_every_configured_redirect_is_a_slug_pair_that_never_points_to_itself(): void
    {
        foreach (require config_path('product_redirects.php') as $old => $new) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $old);
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $new);
            $this->assertNotSame($old, $new);
        }
    }

    private function product(string $name, string $slug, string $status = Product::PUBLICATION_PUBLISHED): void
    {
        $product = Product::create([
            'name' => $name, 'slug' => $slug, 'description' => 'Deskripsi', 'base_price' => 150000,
            'is_active' => $status === Product::PUBLICATION_PUBLISHED, 'publication_status' => $status,
            'published_at' => now(), 'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        ProductVariant::create(['product_id' => $product->id, 'volume' => 100, 'price' => 150000, 'stock' => 0, 'is_active' => true]);
    }
}
