<?php

namespace Tests\Feature;

use App\Actions\Products\ArchiveProductImage;
use App\Actions\Products\AttachProductImage;
use App\Actions\Products\MoveProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'product-media-management-test';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['media.product_disk' => self::DISK]);
        Storage::fake(self::DISK);

        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_admin_can_choose_primary_image_without_changing_identity_or_order(): void
    {
        $product = $this->product('Primary Image');
        [$first, $second] = $this->images($product, 2);
        $identity = [$second->id, $second->image_path, $second->sort_order];
        $returnTo = '/admin/products?page=3&search=Primary';

        $this->actingAs($this->admin)
            ->patch(route('admin.products.images.primary', [$product, $second]), ['return_to' => $returnTo])
            ->assertRedirect(route('admin.products.edit', [
                'product' => $product->id,
                'return_to' => $returnTo,
            ]))
            ->assertSessionHas('success', 'Foto utama berhasil diperbarui.');

        $this->assertSame($identity, [$second->fresh()->id, $second->image_path, $second->sort_order]);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_move_image_swaps_neighbor_and_normalizes_legacy_order_values(): void
    {
        $product = $this->product('Image Ordering');
        [$first, $second, $third] = $this->images($product, 3);
        $first->update(['sort_order' => 10]);
        $second->update(['sort_order' => 20]);
        $third->update(['sort_order' => 30]);

        app(MoveProductImage::class)->handle($product, $third->id, 'up');

        $ordered = $product->images()->get();
        $this->assertSame([$first->id, $third->id, $second->id], $ordered->pluck('id')->all());
        $this->assertSame([0, 1, 2], $ordered->pluck('sort_order')->all());

        app(MoveProductImage::class)->handle($product, $first->id, 'up');
        $this->assertSame([$first->id, $third->id, $second->id], $product->images()->pluck('id')->all());
    }

    public function test_archiving_non_primary_image_soft_deletes_metadata_and_keeps_file(): void
    {
        $product = $this->product('Archive Additional');
        [$first, $second, $third] = $this->images($product, 3);

        $archived = app(ArchiveProductImage::class)->handle($product, $second->id);

        $this->assertTrue($archived->trashed());
        $this->assertSoftDeleted('product_images', ['id' => $second->id]);
        $this->assertSame([$first->id, $third->id], $product->images()->pluck('id')->all());
        $this->assertSame([0, 1], $product->images()->pluck('sort_order')->all());
        Storage::disk(self::DISK)->assertExists($second->image_path);
    }

    public function test_archiving_primary_promotes_next_image_and_keeps_exactly_one_primary(): void
    {
        $product = $this->product('Archive Primary');
        [$first, $second] = $this->images($product, 2);

        app(ArchiveProductImage::class)->handle($product, $first->id);

        $this->assertSoftDeleted('product_images', ['id' => $first->id, 'is_primary' => false]);
        $promoted = $second->fresh();
        $this->assertTrue($promoted->is_primary);
        $this->assertSame(0, $promoted->sort_order);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        Storage::disk(self::DISK)->assertExists($first->image_path);
    }

    public function test_published_product_cannot_archive_its_last_image(): void
    {
        $product = $this->product('Published Last Image', true);
        [$image] = $this->images($product, 1);

        try {
            app(ArchiveProductImage::class)->handle($product, $image->id);
            $this->fail('The final image of a published product must not be archived.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'Foto terakhir produk tayang tidak dapat diarsipkan. Tambahkan foto pengganti terlebih dahulu.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('product_images', ['id' => $image->id, 'deleted_at' => null]);
        $this->assertTrue($image->fresh()->is_primary);
        Storage::disk(self::DISK)->assertExists($image->image_path);
    }

    public function test_draft_can_archive_its_last_image_and_reuse_the_freed_slot(): void
    {
        $product = $this->product('Draft Last Image');
        [$image] = $this->images($product, 1);

        app(ArchiveProductImage::class)->handle($product, $image->id);
        $replacementPath = $this->storedPath('products/replacement.jpg');
        $replacement = app(AttachProductImage::class)->handle($product, $replacementPath);

        $this->assertSoftDeleted('product_images', ['id' => $image->id]);
        $this->assertSame(1, $product->images()->count());
        $this->assertTrue($replacement->is_primary);
        $this->assertSame(0, $replacement->sort_order);
    }

    public function test_nested_media_actions_reject_an_image_owned_by_another_product(): void
    {
        $product = $this->product('Target Product');
        $other = $this->product('Other Product');
        [$otherImage] = $this->images($other, 1);

        foreach ([
            ['patch', 'admin.products.images.primary', []],
            ['patch', 'admin.products.images.move', ['direction' => 'down']],
            ['delete', 'admin.products.images.destroy', []],
        ] as [$method, $routeName, $payload]) {
            $this->actingAs($this->admin)
                ->{$method}(route($routeName, [$product, $otherImage]), $payload)
                ->assertNotFound();
        }

        $this->assertDatabaseHas('product_images', [
            'id' => $otherImage->id,
            'product_id' => $other->id,
            'deleted_at' => null,
        ]);
    }

    public function test_product_editor_exposes_clear_media_states_and_safe_controls(): void
    {
        $fullProduct = $this->product('Full Gallery', true);
        $this->images($fullProduct, 3);

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $fullProduct->id))
            ->assertOk()
            ->assertSee('Foto Produk')
            ->assertSee('3/3')
            ->assertSee('Foto utama')
            ->assertSee('Jadikan utama')
            ->assertSee('Arsipkan foto')
            ->assertSee('Galeri sudah penuh')
            ->assertSee('file tetap disimpan untuk recovery')
            ->assertDontSee('name="new_images[]"', false);

        $emptyDraft = $this->product('Empty Gallery');

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $emptyDraft->id))
            ->assertOk()
            ->assertSee('Belum ada foto produk')
            ->assertSee('Tersisa 3 slot')
            ->assertSee('name="new_images[]"', false);
    }

    public function test_move_action_rejects_invalid_direction_and_foreign_image(): void
    {
        $product = $this->product('Move Target');
        $other = $this->product('Move Other');
        [$image] = $this->images($other, 1);

        try {
            app(MoveProductImage::class)->handle($product, $image->id, 'up');
            $this->fail('A foreign image must not be moved.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->expectException(\InvalidArgumentException::class);
        app(MoveProductImage::class)->handle($other, $image->id, 'sideways');
    }

    private function product(string $name, bool $published = false): Product
    {
        return Product::create([
            'name' => $name,
            'publication_status' => $published ? Product::PUBLICATION_PUBLISHED : Product::PUBLICATION_DRAFT,
            'is_active' => $published,
            'published_at' => $published ? now() : null,
        ]);
    }

    /**
     * @return array<int, ProductImage>
     */
    private function images(Product $product, int $count): array
    {
        $images = [];

        foreach (range(1, $count) as $index) {
            $images[] = app(AttachProductImage::class)->handle(
                $product,
                $this->storedPath("products/{$product->id}-{$index}.jpg")
            );
        }

        return $images;
    }

    private function storedPath(string $path): string
    {
        Storage::disk(self::DISK)->put($path, 'test-image');

        return $path;
    }
}
