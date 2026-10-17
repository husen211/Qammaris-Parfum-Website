<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ORD-08 audit: product price fields show 150.000 while typing; a submit without the JS digits step still saves. */
class AdminProductRupiahInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_thousands_formatted_prices_are_saved_as_whole_rupiah(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.products.create'))->assertOk()->assertSee('data-rupiah-input', false);

        $this->post(route('admin.products.store'), [
            'name' => 'Rupiah Sintetis', 'publication_action' => 'draft', 'compare_at_price' => '150.000',
            'variants' => [['volume' => 50, 'price' => '125.000']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::with('variants')->sole();
        $this->assertSame(['150000.00', '125000.00'], [(string) $product->compare_at_price, (string) $product->variants->sole()->price]);

        // Anything that is not a whole rupiah amount is still refused.
        $this->post(route('admin.products.store'), ['name' => 'Pecahan', 'publication_action' => 'draft', 'variants' => [['volume' => 50, 'price' => '125.5']]])
            ->assertSessionHasErrors('variants.0.price');
    }
}
