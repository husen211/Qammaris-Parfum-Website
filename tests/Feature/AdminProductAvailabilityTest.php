<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminProductAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->brand = Brand::create([
            'name' => 'Availability Brand',
            'is_active' => true,
        ]);
        $this->category = Category::create(['name' => 'EDP']);
    }

    public function test_admin_can_set_sold_out_without_changing_publication_and_return_context(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 20:00:00'));
        $product = $this->createProduct('Manual Sold Out', [
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ]);
        $returnPath = route('admin.products.index', [
            'page' => 2,
            'availability' => Product::AVAILABILITY_UNKNOWN,
        ], false);
        $payload = $this->validUpdatePayload($product, [
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_confirmed' => '0',
            'return_to' => $returnPath,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect($returnPath)
            ->assertSessionHas('success', 'Product updated successfully!');

        $product->refresh();

        $this->assertSame(Product::AVAILABILITY_SOLD_OUT, $product->availability_status);
        $this->assertSame('manual', $product->availability_source);
        $this->assertTrue($product->availability_checked_at->equalTo(now()));
        $this->assertSame(Product::PUBLICATION_PUBLISHED, $product->publication_status);
        $this->assertTrue($product->is_active);
    }

    public function test_ordinary_edit_preserves_availability_time_and_explicit_confirmation_refreshes_it(): void
    {
        $checkedAt = Carbon::parse('2026-09-16 08:00:00');
        $this->travelTo(Carbon::parse('2026-09-16 09:00:00'));
        $product = $this->createProduct('Fresh Availability', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_source' => 'manual',
            'availability_checked_at' => $checkedAt,
        ]);

        $payload = $this->validUpdatePayload($product, [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_confirmed' => '0',
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect(route('admin.products.index'));

        $this->assertTrue($product->fresh()->availability_checked_at->equalTo($checkedAt));

        $this->travelTo(Carbon::parse('2026-09-16 10:00:00'));
        $payload['availability_confirmed'] = '1';

        $this->actingAs($this->admin)
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();

        $this->assertTrue($product->availability_checked_at->equalTo(now()));
        $this->assertSame('manual', $product->availability_source);
    }

    public function test_invalid_availability_is_rejected_without_changing_metadata(): void
    {
        $checkedAt = Carbon::parse('2026-09-16 08:00:00');
        $product = $this->createProduct('Invalid Availability', [
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
            'availability_source' => 'import',
            'availability_checked_at' => $checkedAt,
        ]);
        $payload = $this->validUpdatePayload($product, [
            'availability_status' => 'in_stock',
            'availability_confirmed' => '1',
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.products.edit', $product->id))
            ->put(route('admin.products.update', $product->id), $payload)
            ->assertRedirect(route('admin.products.edit', $product->id))
            ->assertSessionHasErrors('availability_status');

        $product->refresh();

        $this->assertSame(Product::AVAILABILITY_UNKNOWN, $product->availability_status);
        $this->assertSame('import', $product->availability_source);
        $this->assertTrue($product->availability_checked_at->equalTo($checkedAt));
    }

    public function test_catalog_filters_by_effective_availability_and_preserves_filter_in_edit_link(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 20:00:00'));
        $unknown = $this->createProduct('Unknown Product');
        $fresh = $this->createProduct('Fresh Available Product', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(2),
        ]);
        $stale = $this->createProduct('Stale Available Product', [
            'availability_status' => Product::AVAILABILITY_AVAILABLE,
            'availability_checked_at' => now()->subHours(Product::AVAILABILITY_FRESH_HOURS + 1),
        ]);
        $soldOut = $this->createProduct('Sold Out Product', [
            'availability_status' => Product::AVAILABILITY_SOLD_OUT,
            'availability_checked_at' => now()->subDays(10),
        ]);

        $availableContext = ['availability' => Product::AVAILABILITY_AVAILABLE];
        $availableReturnPath = route('admin.products.index', $availableContext, false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', $availableContext))
            ->assertOk()
            ->assertSee($fresh->name)
            ->assertDontSee($unknown->name)
            ->assertDontSee($stale->name)
            ->assertDontSee($soldOut->name)
            ->assertSee(e(route('admin.products.edit', [
                'product' => $fresh->id,
                'return_to' => $availableReturnPath,
            ])), false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['availability' => Product::AVAILABILITY_UNKNOWN]))
            ->assertOk()
            ->assertSee($unknown->name)
            ->assertSee($stale->name)
            ->assertDontSee($fresh->name)
            ->assertDontSee($soldOut->name);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['availability' => Product::AVAILABILITY_SOLD_OUT]))
            ->assertOk()
            ->assertSee($soldOut->name)
            ->assertDontSee($unknown->name)
            ->assertDontSee($fresh->name)
            ->assertDontSee($stale->name);
    }

    public function test_unknown_product_with_zero_snapshot_is_not_presented_as_sold_out(): void
    {
        $product = $this->createProduct('Zero Snapshot Unknown', [], 0);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['search' => $product->name]))
            ->assertOk()
            ->assertSee('Belum dikonfirmasi')
            ->assertSee('Belum pernah diperiksa')
            ->assertDontSee('Out of Stock');
    }

    private function createProduct(string $name, array $overrides = [], int $stock = 0): Product
    {
        $product = Product::create(array_merge([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'description' => 'Product used to verify manual availability controls.',
            'base_price' => 100000,
            'gender' => 'Unisex',
            'is_active' => true,
            'publication_status' => Product::PUBLICATION_PUBLISHED,
            'availability_status' => Product::AVAILABILITY_UNKNOWN,
        ], $overrides));

        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 100000,
            'stock' => $stock,
            'is_active' => true,
        ]);

        return $product;
    }

    private function validUpdatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'compare_at_price' => null,
            'gender' => 'Unisex',
            'top_notes' => '',
            'middle_notes' => '',
            'base_notes' => '',
            'variants' => [[
                'id' => $product->variants()->firstOrFail()->id,
                'volume' => 100,
                'price' => 100000,
                'stock' => 0,
            ]],
        ], $overrides);
    }
}
