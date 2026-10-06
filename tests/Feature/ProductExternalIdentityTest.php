<?php

namespace Tests\Feature;

use App\Actions\Products\MapExternalProductIdentity;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Models\ProductVariant;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ProductExternalIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_identity_schema_is_separate_from_product_and_offer(): void
    {
        $this->assertTrue(Schema::hasColumns('product_external_identities', [
            'id',
            'product_id',
            'provider',
            'external_product_id',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('products', 'shopee_product_id'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'shopee_product_id'));
    }

    public function test_mapping_normalizes_provider_and_preserves_internal_identity_slug_and_sku(): void
    {
        $product = $this->createDraft('Identity Boundary');
        $offer = ProductVariant::create([
            'product_id' => $product->id,
            'volume' => 100,
            'price' => 250000,
            'stock' => 0,
            'sku' => 'CATALOG-001',
            'is_active' => true,
        ]);
        $before = [$product->id, $product->slug, $offer->sku];

        $identity = app(MapExternalProductIdentity::class)->handle($product, ' Shopee ', ' 58161979155 ');

        $this->assertSame('shopee', $identity->provider);
        $this->assertSame('58161979155', $identity->external_product_id);
        $this->assertSame($product->id, $identity->product->id);
        $this->assertSame($before, [
            $product->fresh()->id,
            $product->fresh()->slug,
            $offer->fresh()->sku,
        ]);
    }

    public function test_same_mapping_is_idempotent_and_preserves_mapping_id(): void
    {
        $product = $this->createDraft('Idempotent Identity');
        $action = app(MapExternalProductIdentity::class);

        $first = $action->handle($product, 'shopee', '58161979155');
        $second = $action->handle($product, 'SHOPEE', 58161979155);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('product_external_identities', 1);
    }

    public function test_same_code_can_exist_for_different_providers(): void
    {
        $shopeeProduct = $this->createDraft('Shopee Product');
        $majooProduct = $this->createDraft('Majoo Product');
        $action = app(MapExternalProductIdentity::class);

        $shopee = $action->handle($shopeeProduct, 'shopee', 'PROVIDER-001');
        $majoo = $action->handle($majooProduct, 'majoo', 'PROVIDER-001');

        $this->assertSame('shopee', $shopee->provider);
        $this->assertSame('majoo', $majoo->provider);
        $this->assertDatabaseCount('product_external_identities', 2);
    }

    public function test_same_provider_code_cannot_belong_to_another_product(): void
    {
        $first = $this->createDraft('Original Listing');
        $other = $this->createDraft('Conflicting Listing');
        $action = app(MapExternalProductIdentity::class);
        $action->handle($first, 'shopee', 'SHOPEE-001');

        try {
            $action->handle($other, 'shopee', 'SHOPEE-001');
            $this->fail('Conflicting provider code should be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Kode produk provider sudah terhubung ke product lain.', $exception->getMessage());
        }

        $this->assertDatabaseCount('product_external_identities', 1);
        $this->assertSame($first->id, ProductExternalIdentity::first()->product_id);
    }

    public function test_product_identity_cannot_be_rebound_to_a_new_code_for_same_provider(): void
    {
        $product = $this->createDraft('Recreated Provider Listing');
        $action = app(MapExternalProductIdentity::class);
        $action->handle($product, 'shopee', 'OLD-CODE');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Product sudah mempunyai identity untuk provider ini dan tidak dapat di-rebind.');

        $action->handle($product, 'shopee', 'NEW-CODE');
    }

    public function test_unsupported_provider_and_empty_code_are_rejected_before_write(): void
    {
        $product = $this->createDraft('Invalid Identity');
        $action = app(MapExternalProductIdentity::class);

        try {
            $action->handle($product, 'marketplace-lain', 'CODE-001');
            $this->fail('Unsupported provider should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Provider identity eksternal tidak didukung.', $exception->getMessage());
        }

        try {
            $action->handle($product, 'shopee', '   ');
            $this->fail('Empty provider code should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Kode produk provider wajib diisi dan maksimal 191 karakter.', $exception->getMessage());
        }

        $this->assertDatabaseCount('product_external_identities', 0);
    }

    public function test_database_rejects_duplicate_product_provider_mapping(): void
    {
        $product = $this->createDraft('Database Constraint');
        $product->externalIdentities()->create([
            'provider' => 'shopee',
            'external_product_id' => 'FIRST-CODE',
        ]);

        $this->expectException(QueryException::class);

        $product->externalIdentities()->create([
            'provider' => 'shopee',
            'external_product_id' => 'SECOND-CODE',
        ]);
    }

    public function test_database_rejects_duplicate_code_within_same_provider(): void
    {
        $first = $this->createDraft('First Database Product');
        $other = $this->createDraft('Other Database Product');
        $first->externalIdentities()->create([
            'provider' => 'shopee',
            'external_product_id' => 'DUPLICATE-CODE',
        ]);

        $this->expectException(QueryException::class);

        $other->externalIdentities()->create([
            'provider' => 'shopee',
            'external_product_id' => 'DUPLICATE-CODE',
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
