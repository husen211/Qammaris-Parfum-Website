<?php

namespace Tests\Feature;

use App\Actions\Products\SyncSingleOffer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDraftAndOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_minimal_draft_can_be_saved_with_only_a_working_name(): void
    {
        $draft = Product::create([
            'name' => 'Draft Minimal',
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);

        $draft->refresh();

        $this->assertNotNull($draft->slug);
        $this->assertNull($draft->brand_id);
        $this->assertNull($draft->category_id);
        $this->assertNull($draft->description);
        $this->assertNull($draft->base_price);
        $this->assertNull($draft->gender);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertSame('Harga belum diisi', $draft->price_range);
    }

    public function test_single_offer_is_created_without_random_sku_and_mirrors_price(): void
    {
        $product = $this->createDraft('Offer Baru');

        $offer = app(SyncSingleOffer::class)->handle($product, [
            'volume' => 100,
            'price' => 275000,
            'stock' => 0,
        ]);

        $this->assertNull($offer->sku);
        $this->assertSame('275000.00', $offer->price);
        $this->assertSame('275000.00', $product->fresh()->base_price);
        $this->assertSame($offer->id, $product->fresh()->activeOffer->id);
    }

    public function test_single_offer_update_preserves_offer_identity_and_existing_sku(): void
    {
        $product = $this->createDraft('Offer Existing');
        $offer = ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 50,
            'price' => 200000,
            'stock' => 2,
            'sku' => 'LEGACY-001',
            'is_active' => true,
        ]);

        $updated = app(SyncSingleOffer::class)->handle($product, [
            'id' => $offer->id,
            'volume' => 100,
            'price' => 325000,
            'stock' => 4,
        ]);

        $this->assertSame($offer->id, $updated->id);
        $this->assertSame('LEGACY-001', $updated->sku);
        $this->assertSame(100, $updated->volume);
        $this->assertSame('325000.00', $updated->price);
        $this->assertSame('325000.00', $product->fresh()->base_price);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_single_offer_update_rejects_an_offer_owned_by_another_product(): void
    {
        $product = $this->createDraft('Target Product');
        $other = $this->createDraft('Other Product');
        $otherOffer = ProductVariant::create([
            'product_id' => $other->id,
            'volume' => 100,
            'price' => 200000,
            'stock' => 0,
            'sku' => null,
            'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(SyncSingleOffer::class)->handle($product, [
            'id' => $otherOffer->id,
            'volume' => 100,
            'price' => 999999,
            'stock' => 0,
        ]);
    }

    public function test_database_rejects_a_second_offer_for_the_same_product(): void
    {
        $product = $this->createDraft('Single Offer Constraint');
        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 50,
            'price' => 100000,
            'stock' => 0,
            'sku' => null,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 200000,
            'stock' => 0,
            'sku' => null,
            'is_active' => true,
        ]);
    }

    private function createDraft(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'publication_status' => Product::PUBLICATION_DRAFT,
            'is_active' => false,
        ]);
    }
}
