<?php

namespace Tests\Feature;

use App\Actions\Products\PublishProduct;
use App\Actions\Products\RecordProductAdminChange;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AdminProductAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_creation_records_the_authenticated_actor_not_request_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Audit draft', 'publication_action' => 'draft',
            'description' => 'Private imported copy', 'top_notes' => 'Sensitive note',
            'actor_id' => $other->id, 'password' => 'synthetic-secret-not-for-audit',
            'customer_address' => 'synthetic-customer-not-for-audit',
        ])->assertRedirect();

        $product = Product::sole();
        $audit = DB::table('product_admin_changes')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($product->id, $audit->product_id);
        $this->assertSame('product_created', $audit->action);
        $stored = json_encode($audit, JSON_THROW_ON_ERROR);
        foreach (['Private imported copy', 'Sensitive note', 'synthetic-secret-not-for-audit', 'synthetic-customer-not-for-audit'] as $value) {
            $this->assertStringNotContainsString($value, $stored);
        }
    }

    public function test_update_records_only_changed_allowlisted_fields_and_repeat_is_no_op(): void
    {
        [$admin, $product] = $this->catalog();
        $payload = $this->editorPayload($product);
        $payload['description'] = 'New private copy';
        $payload['top_notes'] = 'New private note';
        $this->actingAs($admin)->put(route('admin.products.update', $product->id), $payload)->assertRedirect();

        $audit = DB::table('product_admin_changes')->sole();
        $before = json_decode($audit->before, true, flags: JSON_THROW_ON_ERROR);
        $after = json_decode($audit->after, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('product_updated', $audit->action);
        $this->assertSame(['description_hash', 'fragrance_notes_hash'], array_keys($after));
        $this->assertNotSame($before['description_hash'], $after['description_hash']);
        $this->assertStringNotContainsString('New private copy', $audit->after);
        $this->assertStringNotContainsString('New private note', $audit->after);
        $this->actingAs($admin)->put(route('admin.products.update', $product->id), $payload)->assertRedirect();
        $this->assertDatabaseCount('product_admin_changes', 1);
    }

    public function test_gallery_records_primary_reorder_and_archive_without_storing_paths(): void
    {
        [$admin, $product, $first, $second] = $this->catalog();
        $this->actingAs($admin)->patch(route('admin.products.images.primary', [$product, $second]))->assertRedirect();
        $this->patch(route('admin.products.images.move', [$product, $second]), ['direction' => 'up'])->assertRedirect();
        $this->delete(route('admin.products.images.destroy', [$product, $second]))->assertRedirect();

        $audits = DB::table('product_admin_changes')->orderBy('id')->get();
        $this->assertSame(['image_primary_changed', 'image_moved', 'image_archived'], $audits->pluck('action')->all());
        foreach ($audits as $audit) {
            $this->assertSame($admin->id, $audit->actor_id);
            $this->assertSame($second->id, $audit->image_id);
            $this->assertSame(['images'], json_decode($audit->changed_fields, true));
            $this->assertStringNotContainsString('products/', $audit->before.$audit->after);
        }
        $last = json_decode($audits->last()->after, true)['images'];
        $this->assertSame([$first->id], array_column($last, 'id'));
        $this->assertTrue($last[0]['is_primary']);
        $this->assertSoftDeleted($second);
        Storage::disk('public')->assertExists($second->image_path);
    }

    public function test_primary_repeat_and_boundary_move_do_not_create_history(): void
    {
        [$admin, $product, $first] = $this->catalog();
        $this->actingAs($admin)->patch(route('admin.products.images.primary', [$product, $first]))->assertRedirect();
        $this->patch(route('admin.products.images.move', [$product, $first]), ['direction' => 'up'])->assertRedirect();
        $this->assertDatabaseCount('product_admin_changes', 0);
    }

    public function test_product_archive_and_restore_preserve_ids_and_record_real_transitions_only(): void
    {
        [$admin, $product] = $this->catalog();
        $slug = $product->slug;
        $this->actingAs($admin)->delete(route('admin.products.destroy', $product->id))->assertRedirect();
        $this->delete(route('admin.products.destroy', $product->id))->assertRedirect();
        $this->patch(route('admin.products.restore', $product->id))->assertRedirect();
        $this->patch(route('admin.products.restore', $product->id))->assertRedirect();

        $audits = DB::table('product_admin_changes')->orderBy('id')->get();
        $this->assertSame(['product_archived', 'product_published'], $audits->pluck('action')->all());
        $this->assertSame($product->id, $audits->first()->product_id);
        $this->assertSame($slug, $product->fresh()->slug);
        $this->assertTrue($product->fresh()->isPublished());
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_validation_and_foreign_image_failures_do_not_create_history(): void
    {
        [$admin, $product, $first] = $this->catalog();
        $other = Product::create(['name' => 'Other draft', 'is_active' => false, 'publication_status' => 'draft']);
        $this->actingAs($admin)->put(route('admin.products.update', $product->id), ['name' => ''])->assertSessionHasErrors();
        $this->patch(route('admin.products.images.primary', [$other, $first]))->assertNotFound();
        $this->patch(route('admin.products.images.move', [$product, $first]), ['direction' => 'sideways'])->assertSessionHasErrors();
        $this->assertDatabaseCount('product_admin_changes', 0);
        $this->assertSame('Audit catalog', $product->fresh()->name);
        $this->assertTrue($first->fresh()->is_primary);
    }

    public function test_forbidden_human_routes_do_not_write_history_or_product_changes(): void
    {
        [, $product, $first] = $this->catalog();
        $customer = User::factory()->create();
        $this->actingAs($customer)->put(route('admin.products.update', $product->id), $this->editorPayload($product))->assertForbidden();
        $this->delete(route('admin.products.destroy', $product->id))->assertForbidden();
        $this->patch(route('admin.products.restore', $product->id))->assertForbidden();
        $this->patch(route('admin.products.images.primary', [$product, $first]))->assertForbidden();
        $this->patch(route('admin.products.images.move', [$product, $first]), ['direction' => 'down'])->assertForbidden();
        $this->delete(route('admin.products.images.destroy', [$product, $first]))->assertForbidden();
        $this->assertDatabaseCount('product_admin_changes', 0);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertTrue($product->fresh()->isPublished());
    }

    public function test_audit_failure_rolls_back_editor_and_cleans_only_new_uploaded_file(): void
    {
        [$admin, $product, $first] = $this->catalog();
        $payload = $this->editorPayload($product);
        $payload['name'] = 'Must roll back';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $payload['new_images'] = [UploadedFile::fake()->createWithContent('new.png', $png)];
        $this->mock(RecordProductAdminChange::class)->makePartial()->shouldReceive('handle')->once()
            ->andThrow(new RuntimeException('Synthetic audit failure'));
        $this->actingAs($admin)->put(route('admin.products.update', $product->id), $payload)->assertSessionHas('error');
        $this->assertSame('Audit catalog', $product->fresh()->name);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertDatabaseCount('product_admin_changes', 0);
        $this->assertCount(2, Storage::disk('public')->allFiles('products'));
        Storage::disk('public')->assertExists($first->image_path);
    }

    public function test_audit_failure_rolls_back_gallery_primary_archive_and_reorder(): void
    {
        [$admin, $product, $first, $second] = $this->catalog();
        $this->actingAs($admin)->withoutExceptionHandling();
        $this->mock(RecordProductAdminChange::class)->makePartial()->shouldReceive('handle')->times(3)
            ->andThrow(new RuntimeException('Synthetic audit failure'));
        foreach (['primary', 'move', 'destroy'] as $action) {
            try {
                $route = route('admin.products.images.'.$action, [$product, $second]);
                $action === 'destroy' ? $this->delete($route) : $this->patch($route, ['direction' => 'up']);
                $this->fail('Audit failure must reject the mutation.');
            } catch (RuntimeException $error) {
                $this->assertSame('Synthetic audit failure', $error->getMessage());
            }
            $this->assertTrue($first->fresh()->is_primary);
            $this->assertFalse($second->fresh()->is_primary);
            $this->assertNull($second->fresh()->deleted_at);
            $this->assertSame(1, $second->fresh()->sort_order);
            $this->assertDatabaseCount('product_admin_changes', 0);
            Storage::disk('public')->assertExists($second->image_path);
        }
    }

    public function test_outer_rollback_removes_both_mutation_and_nested_audit(): void
    {
        [$admin, $product] = $this->catalog();
        $product->markArchived();
        try {
            DB::transaction(function () use ($admin, $product): void {
                app(PublishProduct::class)->handle($product, $admin);
                $this->assertDatabaseCount('product_admin_changes', 1);
                throw new RuntimeException('Outer rollback');
            });
        } catch (RuntimeException $error) {
            $this->assertSame('Outer rollback', $error->getMessage());
        }
        $this->assertSame(Product::PUBLICATION_ARCHIVED, $product->fresh()->publication_status);
        $this->assertDatabaseCount('product_admin_changes', 0);
    }

    public function test_system_publication_does_not_fabricate_human_attribution(): void
    {
        [, $product] = $this->catalog();
        $product->markArchived();
        app(PublishProduct::class)->handle($product);
        $this->assertTrue($product->fresh()->isPublished());
        $this->assertDatabaseCount('product_admin_changes', 0);
    }

    public function test_last_published_image_remains_protected_and_does_not_create_audit(): void
    {
        [$admin, $product, $first, $second] = $this->catalog();
        $this->actingAs($admin)->delete(route('admin.products.images.destroy', [$product, $second]))->assertRedirect();
        $this->delete(route('admin.products.images.destroy', [$product, $first]))->assertSessionHas('error');
        $this->assertDatabaseCount('product_admin_changes', 1);
        $this->assertNull($first->fresh()->deleted_at);
        Storage::disk('public')->assertExists($first->image_path);
    }

    public function test_archive_audit_failure_keeps_the_product_published(): void
    {
        [$admin, $product] = $this->catalog();
        $this->actingAs($admin)->withoutExceptionHandling();
        $this->mock(RecordProductAdminChange::class)->makePartial()->shouldReceive('handle')->once()
            ->andThrow(new RuntimeException('Synthetic audit failure'));
        try {
            $this->delete(route('admin.products.destroy', $product->id));
            $this->fail('Audit failure must reject archive.');
        } catch (RuntimeException $error) {
            $this->assertSame('Synthetic audit failure', $error->getMessage());
        }
        $this->assertTrue($product->fresh()->isPublished());
        $this->assertDatabaseCount('product_admin_changes', 0);
    }

    public function test_historical_actor_and_product_ids_are_retained_without_cascade(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Retention draft', 'publication_action' => 'draft',
        ])->assertRedirect();
        $product = Product::sole();
        $productId = $product->id;
        $actorId = $admin->id;
        // Synthetic test-only deletions exercise retention; application routes still archive products.
        $product->delete();
        $admin->delete();
        $this->assertDatabaseCount('product_admin_changes', 1);
        $this->assertDatabaseHas('product_admin_changes', ['product_id' => $productId, 'actor_id' => $actorId]);
    }

    private function catalog(): array
    {
        $this->withoutVite();
        config(['media.product_disk' => 'public']);
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $brand = Brand::create(['name' => 'Audit brand', 'is_active' => true]);
        $category = Category::create(['name' => 'EDP']);
        $product = Product::create([
            'name' => 'Audit catalog', 'brand_id' => $brand->id, 'category_id' => $category->id,
            'description' => 'Original copy', 'fragrance_notes' => ['top' => [], 'middle' => [], 'base' => []],
            'gender' => 'Unisex', 'base_price' => 100000, 'publication_status' => 'published',
            'is_active' => true, 'availability_source' => 'manual', 'availability_status' => 'available',
            'availability_checked_at' => now(),
        ]);
        $product->variants()->create(['volume' => 100, 'price' => 100000, 'stock' => 0, 'is_active' => true]);
        $images = [];
        for ($i = 0; $i < 2; $i++) {
            $path = 'products/audit-'.$i.'.png';
            Storage::disk('public')->put($path, 'synthetic-image');
            $images[] = $product->images()->create(['image_path' => $path, 'is_primary' => $i === 0, 'sort_order' => $i]);
        }

        return [$admin, $product, ...$images];
    }

    private function editorPayload(Product $product): array
    {
        $offer = $product->variants()->sole();

        return [
            'name' => $product->name, 'brand_id' => $product->brand_id, 'category_id' => $product->category_id,
            'description' => $product->description, 'gender' => $product->gender,
            'variants' => [['id' => $offer->id, 'volume' => 100, 'price' => 100000]],
        ];
    }
}
