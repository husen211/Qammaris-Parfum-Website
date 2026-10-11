<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FragranceProfile;
use App\Models\FragranceProfileRevision;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FragrancePreferenceCatalog;
use App\Services\FragranceProfileStore;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FragranceProfileStoreTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private User $admin;

    private FragranceProfileStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = app(FragranceProfileStore::class);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
        $brand = Brand::create(['name' => 'Synthetic profile brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $this->product = Product::create(['brand_id' => $brand->id, 'category_id' => $category->id, 'name' => 'Synthetic citrus', 'description' => 'Kekuatan Aroma: Kuat', 'fragrance_notes' => ['top' => ['Bergamot'], 'middle' => ['Jasmine'], 'base' => ['Musk']], 'gender' => 'Unisex', 'publication_status' => 'published', 'is_active' => true, 'availability_status' => 'available', 'availability_source' => 'qammaris_app']);
        ProductVariant::create(['product_id' => $this->product->id, 'volume' => 50, 'price' => 200000, 'stock' => 0, 'is_active' => true]);
    }

    private function answers(): array
    {
        return ['budget_max' => 300000, 'likes' => ['citrus'], 'avoid' => [], 'use' => 'any', 'environment' => 'any', 'sweetness' => 'any', 'projection' => 'strong', 'longevity' => 'not_priority'];
    }

    private function build(): FragranceProfile
    {
        $this->store->apply($this->store->preview()['fingerprint'], $this->admin);

        return FragranceProfile::where('product_id', $this->product->id)->firstOrFail();
    }

    public function test_preview_has_no_writes_apply_is_idempotent_and_catalog_is_untouched(): void
    {
        $original = $this->product->refresh()->getRawOriginal();
        $offer = $this->product->activeOffer->getRawOriginal();
        $preview = $this->store->preview();
        $this->assertDatabaseCount('fragrance_profiles', 0);
        $this->assertDatabaseCount('fragrance_profile_revisions', 0);
        $this->assertSame(1, $this->store->apply($preview['fingerprint'], $this->admin)['changed']);
        $this->assertSame(0, $this->store->apply($this->store->preview()['fingerprint'], $this->admin)['changed']);
        $this->assertDatabaseCount('fragrance_profile_revisions', 1);
        $this->assertSame($original, $this->product->fresh()->getRawOriginal());
        $this->assertSame($offer, $this->product->fresh()->activeOffer->getRawOriginal());
    }

    public function test_source_change_rejects_old_preview_without_writing_profiles(): void
    {
        $preview = $this->store->preview();
        $this->product->update(['description' => 'Changed source']);
        try {
            $this->store->apply($preview['fingerprint'], $this->admin);
            $this->fail('Stale preview accepted.');
        } catch (DomainException) {
            $this->assertDatabaseCount('fragrance_profiles', 0);
            $this->assertDatabaseCount('fragrance_profile_revisions', 0);
        }
    }

    public function test_review_requires_existing_admin_and_current_revision(): void
    {
        $profile = $this->build();
        $ordinary = User::factory()->create();
        try {
            $this->store->review($this->product->id, $profile->revision, $profile->source_fingerprint, ['sweetness' => 'light'], 'Staff observed a light sweetness.', $ordinary);
            $this->fail('Non-admin review accepted.');
        } catch (DomainException) {
            $this->assertDatabaseCount('fragrance_profile_revisions', 1);
        }
        $reviewed = $this->store->review($this->product->id, $profile->revision, $profile->source_fingerprint, ['sweetness' => 'light'], 'Synthetic staff evidence for test only.', $this->admin);
        $this->assertSame('light', $this->store->effective($reviewed, $this->product)['attributes']['sweetness']);
        $this->assertSame(2, $reviewed->revision);
        $again = $this->store->review($this->product->id, $reviewed->revision, $profile->source_fingerprint, ['sweetness' => 'light'], 'Synthetic staff evidence for test only.', $this->admin);
        $this->assertSame(2, $again->revision);
        $this->expectException(DomainException::class);
        $this->store->review($this->product->id, 1, $profile->source_fingerprint, ['sweetness' => 'sweet'], 'Attempt against obsolete revision.', $this->admin);
    }

    public function test_source_change_blocks_profile_and_old_override_is_retained_for_review_only(): void
    {
        $profile = $this->build();
        $profile = $this->store->review($this->product->id, $profile->revision, $profile->source_fingerprint, ['sweetness' => 'light', 'identity' => 'verified-synthetic'], 'Synthetic review basis for old source.', $this->admin);
        $this->product->update(['fragrance_notes' => ['top' => ['Rose'], 'middle' => ['Jasmine'], 'base' => ['Musk']]]);
        $this->assertNull($this->store->effective($profile, $this->product));
        $preview = $this->store->preview();
        $this->assertSame(2, $preview['rows'][0]['stale_overrides']);
        $this->store->apply($preview['fingerprint'], $this->admin);
        $effective = $this->store->effective($profile->fresh(), $this->product);
        $this->assertNull($effective['attributes']['sweetness']);
        $this->assertArrayNotHasKey('verified_identity', $effective);
        $this->assertContains('stale_override', array_column($effective['review_issues'], 'code'));
        $this->assertNotEmpty($profile->fresh()->overrides);
    }

    public function test_revision_write_failure_rolls_back_entire_profile_apply(): void
    {
        FragranceProfileRevision::creating(function () {
            throw new \RuntimeException('Synthetic storage failure');
        });
        try {
            $this->store->apply($this->store->preview()['fingerprint'], $this->admin);
            $this->fail('Failure not surfaced.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('fragrance_profiles', 0);
            $this->assertDatabaseCount('fragrance_profile_revisions', 0);
        } finally {
            FragranceProfileRevision::flushEventListeners();
        }
    }

    public function test_current_catalog_price_availability_visibility_and_offer_guard_are_authoritative(): void
    {
        $this->build();
        config(['fragrance_preference.enabled' => true]);
        $catalog = app(FragrancePreferenceCatalog::class);
        $this->assertSame('200000.00', $catalog->recommend($this->answers())['main'][0]['price']);
        $this->product->activeOffer->update(['price' => 325000]);
        $this->assertSame([], $catalog->recommend($this->answers())['main']);
        $this->assertSame('325000.00', $catalog->recommend($this->answers())['alternative'][0]['price']);
        $this->product->update(['availability_status' => 'sold_out']);
        $this->assertSame('sold_out', $catalog->recommend($this->answers())['alternative'][0]['availability']);
        $this->assertTrue($catalog->recommend($this->answers(), true)['empty']);
        $this->product->forceFill(['qammaris_app_hidden' => true])->save();
        $this->assertTrue($catalog->recommend($this->answers())['empty']);
    }

    public function test_disabled_flag_and_unreviewed_default_keep_public_engine_disconnected(): void
    {
        $this->assertFalse(config('fragrance_preference.enabled'));
        $this->expectException(DomainException::class);
        app(FragrancePreferenceCatalog::class)->recommend($this->answers());
    }

    public function test_command_defaults_to_preview_and_requires_actor_and_fingerprint_to_apply(): void
    {
        $this->artisan('fragrance-profiles:build')->assertExitCode(0);
        $this->assertDatabaseCount('fragrance_profiles', 0);
        $this->artisan('fragrance-profiles:build', ['--apply' => true])->assertExitCode(1);
        $this->assertDatabaseCount('fragrance_profiles', 0);
        $this->artisan('fragrance-profiles:build', ['--apply' => true, '--preview' => $this->store->preview()['fingerprint'], '--actor' => $this->admin->id])->assertExitCode(0);
        $this->assertDatabaseCount('fragrance_profiles', 1);
    }
}
