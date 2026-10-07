<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelevantSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_product_search_excludes_description_mentions_handles_typos_and_preserves_filters(): void
    {
        $target = $this->product('Zimaya Reverie Aqua EDP 100ML');
        $this->product('SAFF CHNO', ['description' => 'Experience a reverie with this scent.']);
        $draft = $this->product('Reverie Draft', ['publication_status' => 'draft']);
        $this->product('Reverie Hidden')->forceFill(['qammaris_app_hidden' => true])->save();
        foreach (['reverie', 'rverie', 'reverei', 'zimya rverie aqua 100ml'] as $search) {
            $result = $this->get(route('products.index', compact('search')))->assertOk();
            $this->assertSame([$target->id], $result->viewData('products')->pluck('id')->all());
        }
        foreach (['reverie 50ml', '%', 'unknown-no-match'] as $search) {
            $this->assertSame(0, $this->get(route('products.index', compact('search')))->viewData('products')->total());
        }
        $this->assertSame(0, $this->get(route('products.index', ['search' => 'rverie', 'gender' => 'Wanita']))->viewData('products')->total());
        $plain = $this->product('Kaaf');
        $this->assertSame([$plain->id], $this->get('/products?search=kaaf+100ml')->viewData('products')->pluck('id')->all());
        $this->assertSame(0, $this->get('/products?search=kaaf+50ml')->viewData('products')->total());
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertSame([$draft->id], $this->get(route('admin.products.index', ['search' => 'rverie', 'publication' => 'draft']))->viewData('products')->pluck('id')->all());
        $target->variants()->first()->update(['sku' => 'EXACT-SKU']);
        $this->assertSame([$target->id], $this->get(route('admin.products.index', ['search' => 'exact-sku']))->viewData('products')->pluck('id')->all());
        $this->assertSame(0, $this->get(route('admin.products.index', ['search' => 'EXACT-SKY']))->viewData('products')->total());
    }

    public function test_relevance_precedes_merchandising_but_explicit_price_and_latest_sorts_remain(): void
    {
        $exact = $this->product('Reverie Aqua', ['published_at' => now()->subDay()]);
        $close = $this->product('Reveria Aqua', ['is_best_seller' => true], 100000);
        $this->assertSame([$exact->id, $close->id], $this->get('/products?search=reverie')->viewData('products')->pluck('id')->all());
        $this->assertSame([$close->id, $exact->id], $this->get('/products?search=reverie&sort=price_low')->viewData('products')->pluck('id')->all());
        $this->assertSame([$close->id, $exact->id], $this->get('/products?search=reverie&sort=latest')->viewData('products')->pluck('id')->all());
    }

    public function test_fuzzy_results_paginate_stably_and_keep_original_search_context(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->product('Reverie Aqua Edition '.$i);
        }
        $first = $this->get('/products?search=rverie')->viewData('products');
        $second = $this->get('/products?search=rverie&page=2')->viewData('products');
        $this->assertSame(30, $first->total());
        $this->assertCount(24, $first->items());
        $this->assertCount(6, $second->items());
        $this->assertSame([], array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        $this->assertStringContainsString('search=rverie', $first->nextPageUrl());
    }

    public function test_taxonomy_and_blog_search_share_typos_and_reject_malformed_terms(): void
    {
        $brand = Brand::create(['name' => 'Afnan', 'is_active' => true]);
        $category = Category::create(['name' => 'Eau de Parfum', 'is_active' => true]);
        $post = BlogPost::create(['title' => 'Panduan Reverie', 'excerpt' => 'Memilih parfum.', 'content' => 'Body only secretterm', 'category' => 'Panduan']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertSame([$brand->id], $this->get('/admin/brands?search=afnna')->viewData('brands')->pluck('id')->all());
        $this->assertSame([$category->id], $this->get('/admin/categories?search=eau+de+prfum')->viewData('categories')->pluck('id')->all());
        $this->assertSame([$post->id], $this->get('/admin/blog-posts?search=rverie')->viewData('posts')->pluck('id')->all());
        $this->assertSame(0, $this->get('/admin/blog-posts?search=secretterm')->viewData('posts')->total());
        foreach (['brands', 'categories', 'blog-posts'] as $path) {
            $this->get('/admin/'.$path.'?search[]=bad')->assertOk();
        }
    }

    private function product(string $name, array $overrides = [], int $price = 300000): Product
    {
        $product = Product::create($overrides + ['name' => $name, 'brand_id' => Brand::firstOrCreate(['name' => 'Zimaya'], ['is_active' => true])->id,
            'category_id' => Category::firstOrCreate(['name' => 'Eau de Parfum'], ['is_active' => true])->id,
            'publication_status' => 'published', 'is_active' => true, 'published_at' => now(), 'gender' => 'Pria']);
        $product->variants()->create(['volume' => 100, 'price' => $price, 'is_active' => true]);

        return $product;
    }
}
