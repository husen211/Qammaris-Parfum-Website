<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\AttachProductImage;
use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\PublishProduct;
use App\Actions\Products\SyncSingleOffer;
use App\Exceptions\ProductNotReadyForPublication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductMediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AdminProductController extends Controller
{
    private const CATALOG_SORTS = ['latest', 'name_asc', 'name_desc'];

    // 1. INDEX: List Produk + Search + Filter Brand
    public function index(Request $request)
    {
        $catalogContext = $this->normalizeCatalogContext($request->query());
        $query = Product::with(['brand', 'category', 'primaryImage', 'variants']);

        // Logic Search
        if (isset($catalogContext['search'])) {
            $query->search($catalogContext['search']);
        }

        // Logic Filter Brand (BARU)
        if (isset($catalogContext['brand_id'])) {
            $query->where('brand_id', $catalogContext['brand_id']);
        }

        if (isset($catalogContext['availability'])) {
            $this->applyAvailabilityFilter($query, $catalogContext['availability']);
        }

        if (isset($catalogContext['publication'])) {
            $query->where('publication_status', $catalogContext['publication']);
        }

        match ($catalogContext['sort'] ?? 'latest') {
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $currentPage = $catalogContext['page'] ?? 1;
        $products = $query->paginate(10, ['*'], 'page', $currentPage);

        if ($currentPage > $products->lastPage()) {
            if ($products->lastPage() > 1) {
                $catalogContext['page'] = $products->lastPage();
            } else {
                unset($catalogContext['page']);
            }

            return redirect()->route('admin.products.index', $catalogContext);
        }

        $products->appends($catalogContext);

        // Ambil data brand untuk dropdown filter
        $brands = Brand::orderBy('name', 'asc')->get();
        $catalogReturnPath = route('admin.products.index', $catalogContext, false);

        return view('admin.products.index', compact(
            'products',
            'brands',
            'catalogContext',
            'catalogReturnPath'
        ));
    }

    // ... (SISA FUNCTION CREATE, STORE, EDIT, UPDATE, DESTROY TETAP SAMA SEPERTI SEBELUMNYA)
    // Pastikan kamu menyalin function lainnya dari kode sebelumnya jika belum ada.

    // Copy function create() sampai destroyImage() dari percakapan sebelumnya ke sini.
    // Kode di bawah hanya referensi function yang diubah (index).

    public function create()
    {
        $brands = Brand::where('is_active', true)->orderBy('name')->get();
        $categories = Category::all();

        return view('admin.products.create', compact('brands', 'categories'));
    }

    public function store(
        ProductStoreRequest $request,
        SyncSingleOffer $syncSingleOffer,
        AttachProductImage $attachProductImage,
        PublishProduct $publishProduct,
        ProductMediaStorage $productMediaStorage
    ) {
        // ... (Gunakan kode STORE dari jawaban sebelumnya)
        $storedImagePaths = [];

        try {
            DB::beginTransaction();
            $compareAtPrice = $request->filled('compare_at_price')
                ? $request->compare_at_price
                : null;

            $fragranceNotes = $this->normalizeFragranceNotes($request);

            $product = Product::create([
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'base_price' => null,
                'compare_at_price' => $compareAtPrice,
                'fragrance_notes' => $fragranceNotes,
                'gender' => $request->gender,
                'is_best_seller' => $request->has('is_best_seller'),
                'is_active' => false,
                'publication_status' => Product::PUBLICATION_DRAFT,
                'published_at' => null,
                'availability_status' => Product::AVAILABILITY_UNKNOWN,
            ]);

            $offerData = $this->completeOfferData($request->validated('variants', []));
            if ($offerData !== null) {
                $syncSingleOffer->handle($product, $offerData);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $this->storeProductImage($image, $storedImagePaths, $productMediaStorage);
                    $attachProductImage->handle($product, $path, $index === 0);
                }
            }

            $publicationAction = $request->input('publication_action', Product::PUBLICATION_PUBLISHED);
            if ($publicationAction === Product::PUBLICATION_PUBLISHED) {
                $publishProduct->handle($product);
            }

            DB::commit();

            if ($publicationAction === Product::PUBLICATION_DRAFT) {
                return redirect()->route('admin.products.edit', $product->id)
                    ->with('success', 'Draft berhasil disimpan. Lengkapi data sebelum dipublikasikan.');
            }

            return redirect()->route('admin.products.index')->with('success', 'Product created successfully!');

        } catch (ProductNotReadyForPublication $e) {
            DB::rollback();
            $this->cleanupStoredImages($storedImagePaths, $productMediaStorage);

            return back()->withErrors($this->publicationErrors($e))->withInput();
        } catch (Throwable $e) {
            DB::rollback();
            $this->cleanupStoredImages($storedImagePaths, $productMediaStorage);
            report($e);

            return back()->with('error', 'Produk gagal disimpan. Silakan coba lagi.')->withInput();
        }
    }

    public function edit(
        Request $request,
        $id,
        EvaluateProductPublicationReadiness $evaluatePublicationReadiness
    ) {
        $product = Product::with(['variants', 'images', 'brand', 'category'])->findOrFail($id);
        $brands = Brand::where('is_active', true)
            ->orWhere('id', $product->brand_id)
            ->orderBy('name')
            ->get();
        $categories = Category::all();
        $catalogReturnPath = $this->catalogReturnPath($request->query('return_to'));
        $publicationBlockers = $evaluatePublicationReadiness->handle($product);

        return view('admin.products.edit', compact(
            'product',
            'brands',
            'categories',
            'catalogReturnPath',
            'publicationBlockers'
        ));
    }

    public function update(
        ProductUpdateRequest $request,
        $id,
        SyncSingleOffer $syncSingleOffer,
        AttachProductImage $attachProductImage,
        PublishProduct $publishProduct,
        ProductMediaStorage $productMediaStorage
    ) {
        // ... (Gunakan kode UPDATE dari jawaban sebelumnya)
        $product = Product::findOrFail($id);
        $storedImagePaths = [];

        try {
            DB::beginTransaction();
            $compareAtPrice = $request->filled('compare_at_price')
                ? $request->compare_at_price
                : null;

            $fragranceNotes = $this->normalizeFragranceNotes($request);

            $product->update([
                'brand_id' => $request->brand_id,
                'category_id' => $request->category_id,
                'name' => $request->name,
                'description' => $request->description,
                'compare_at_price' => $compareAtPrice,
                'fragrance_notes' => $fragranceNotes,
                'gender' => $request->gender,
                'is_best_seller' => $request->has('is_best_seller'),
            ]);

            if ($request->has('availability_status')) {
                $availabilityStatus = $request->validated('availability_status');

                if ($availabilityStatus !== $product->availability_status
                    || $request->boolean('availability_confirmed')) {
                    $product->update([
                        'availability_status' => $availabilityStatus,
                        'availability_source' => 'manual',
                        'availability_checked_at' => now(),
                    ]);
                }
            }

            $offerData = $this->completeOfferData($request->validated('variants', []));
            if ($offerData !== null) {
                $syncSingleOffer->handle($product, $offerData);
            }

            if ($request->hasFile('new_images')) {
                foreach ($request->file('new_images') as $image) {
                    $path = $this->storeProductImage($image, $storedImagePaths, $productMediaStorage);
                    $attachProductImage->handle($product, $path);
                }
            }

            if ($request->input('publication_action') === Product::PUBLICATION_PUBLISHED) {
                $publishProduct->handle($product);
            }

            DB::commit();

            return redirect()->to($this->catalogReturnPath($request->input('return_to')))
                ->with('success', 'Product updated successfully!');

        } catch (ProductNotReadyForPublication $e) {
            DB::rollback();
            $this->cleanupStoredImages($storedImagePaths, $productMediaStorage);

            return back()->withErrors($this->publicationErrors($e))->withInput();
        } catch (Throwable $e) {
            DB::rollback();
            $this->cleanupStoredImages($storedImagePaths, $productMediaStorage);
            report($e);

            return back()->with('error', 'Produk gagal diperbarui. Silakan coba lagi.')->withInput();
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->publication_status === Product::PUBLICATION_ARCHIVED && ! $product->is_active) {
            return back()->with('success', 'Produk ini sudah diarsipkan.');
        }

        $product->markArchived();

        return back()->with('success', 'Produk berhasil diarsipkan. Data dan gambar tetap tersimpan.');
    }

    public function restore(Request $request, $id, PublishProduct $publishProduct)
    {
        $product = Product::findOrFail($id);
        $catalogReturnPath = $this->catalogReturnPath($request->input('return_to'));

        if ($product->isPublished()) {
            return redirect()->to($catalogReturnPath)->with('success', 'Produk ini sudah aktif.');
        }

        try {
            $publishProduct->handle($product);
        } catch (ProductNotReadyForPublication $e) {
            return redirect()->route('admin.products.edit', [
                'product' => $product->id,
                'return_to' => $catalogReturnPath,
            ])->withErrors($this->publicationErrors($e));
        }

        return redirect()->to($catalogReturnPath)->with('success', 'Produk berhasil diaktifkan kembali.');
    }

    public function destroyImage($id)
    {
        ProductImage::findOrFail($id);

        return back()->with(
            'error',
            'Penghapusan gambar dinonaktifkan sementara agar file dan metadata tetap aman.'
        );
    }

    private function storeProductImage(
        UploadedFile $image,
        array &$storedImagePaths,
        ProductMediaStorage $productMediaStorage
    ): string {
        $path = $productMediaStorage->store($image);

        $storedImagePaths[] = $path;

        return $path;
    }

    private function cleanupStoredImages(
        array $storedImagePaths,
        ProductMediaStorage $productMediaStorage
    ): void {
        if ($storedImagePaths === []) {
            return;
        }

        try {
            if (! $productMediaStorage->delete($storedImagePaths)) {
                report(new RuntimeException('One or more rolled-back product images could not be deleted.'));
            }
        } catch (Throwable $cleanupError) {
            report($cleanupError);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, int|string>
     */
    private function normalizeCatalogContext(array $query): array
    {
        $context = [];

        $page = $this->positiveInteger($query['page'] ?? null);
        if ($page !== null && $page > 1) {
            $context['page'] = $page;
        }

        $search = $query['search'] ?? null;
        if (is_string($search)) {
            $search = trim($search);

            if ($search !== '') {
                $context['search'] = mb_substr($search, 0, 100);
            }
        }

        $brandId = $this->positiveInteger($query['brand_id'] ?? null);
        if ($brandId !== null && Brand::whereKey($brandId)->exists()) {
            $context['brand_id'] = $brandId;
        }

        $availability = $query['availability'] ?? null;
        if (is_string($availability) && in_array($availability, [
            Product::AVAILABILITY_UNKNOWN,
            Product::AVAILABILITY_AVAILABLE,
            Product::AVAILABILITY_SOLD_OUT,
        ], true)) {
            $context['availability'] = $availability;
        }

        $publication = $query['publication'] ?? null;
        if (is_string($publication) && in_array($publication, [
            Product::PUBLICATION_DRAFT,
            Product::PUBLICATION_PUBLISHED,
            Product::PUBLICATION_ARCHIVED,
        ], true)) {
            $context['publication'] = $publication;
        }

        $sort = $query['sort'] ?? null;
        if (is_string($sort) && in_array($sort, self::CATALOG_SORTS, true) && $sort !== 'latest') {
            $context['sort'] = $sort;
        }

        return $context;
    }

    private function catalogReturnPath(mixed $returnTo): string
    {
        $indexPath = route('admin.products.index', [], false);

        if (! is_string($returnTo) || $returnTo === '' || strlen($returnTo) > 2048) {
            return $indexPath;
        }

        $parts = parse_url($returnTo);
        if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['fragment'])
            || ($parts['path'] ?? '') !== $indexPath) {
            return $indexPath;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return route('admin.products.index', $this->normalizeCatalogContext($query), false);
    }

    private function applyAvailabilityFilter(Builder $query, string $availability): void
    {
        if ($availability === Product::AVAILABILITY_AVAILABLE) {
            $query
                ->where('availability_status', Product::AVAILABILITY_AVAILABLE)
                ->whereNotNull('availability_checked_at')
                ->where('availability_checked_at', '>=', now()->subHours(Product::AVAILABILITY_FRESH_HOURS));

            return;
        }

        if ($availability === Product::AVAILABILITY_SOLD_OUT) {
            $query->where('availability_status', Product::AVAILABILITY_SOLD_OUT);

            return;
        }

        $freshnessThreshold = now()->subHours(Product::AVAILABILITY_FRESH_HOURS);

        $query->where(function (Builder $availabilityQuery) use ($freshnessThreshold): void {
            $availabilityQuery
                ->whereNull('availability_status')
                ->orWhere('availability_status', Product::AVAILABILITY_UNKNOWN)
                ->orWhere(function (Builder $staleAvailableQuery) use ($freshnessThreshold): void {
                    $staleAvailableQuery
                        ->where('availability_status', Product::AVAILABILITY_AVAILABLE)
                        ->where(function (Builder $checkedAtQuery) use ($freshnessThreshold): void {
                            $checkedAtQuery
                                ->whereNull('availability_checked_at')
                                ->orWhere('availability_checked_at', '<', $freshnessThreshold);
                        });
                });
        });
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $integer === false ? null : $integer;
    }

    /**
     * @param  array<int|string, mixed>  $variants
     * @return array<string, mixed>|null
     */
    private function completeOfferData(array $variants): ?array
    {
        $offerData = array_values($variants)[0] ?? null;

        if (! is_array($offerData)
            || ! isset($offerData['volume'], $offerData['price'])
            || $offerData['volume'] === ''
            || $offerData['price'] === '') {
            return null;
        }

        return $offerData;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function publicationErrors(ProductNotReadyForPublication $exception): array
    {
        return ['publication' => array_values($exception->blockers())];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function normalizeFragranceNotes(Request $request): array
    {
        return collect(['top', 'middle', 'base'])->mapWithKeys(function (string $group) use ($request): array {
            $notes = array_filter(
                array_map('trim', explode(',', (string) $request->input("{$group}_notes", ''))),
                fn (string $note): bool => $note !== ''
            );

            return [$group => array_values($notes)];
        })->all();
    }
}
