<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\EvaluateProductPublicationReadiness;
use App\Actions\Products\PublishProduct;
use App\Actions\Products\SaveProductEditor;
use App\Exceptions\ProductNotReadyForPublication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Rupiah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class AdminProductController extends Controller
{
    private const CATALOG_SORTS = ['latest', 'name_asc', 'name_desc'];

    public function index(Request $request)
    {
        $catalogContext = $this->normalizeCatalogContext($request->query());
        $query = Product::with(['brand', 'category', 'primaryImage', 'variants']);

        if (isset($catalogContext['brand_id'])) {
            $query->where('brand_id', $catalogContext['brand_id']);
        }

        if (isset($catalogContext['availability'])) {
            $this->applyAvailabilityFilter($query, $catalogContext['availability']);
        }

        if (isset($catalogContext['publication'])) {
            $query->where('publication_status', $catalogContext['publication']);
        }

        if (isset($catalogContext['search'])) {
            $query->search($catalogContext['search'], ($catalogContext['sort'] ?? 'latest') === 'latest');
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

        $brands = Brand::orderBy('name', 'asc')->get();
        $catalogReturnPath = route('admin.products.index', $catalogContext, false);

        return view('admin.products.index', compact(
            'products',
            'brands',
            'catalogContext',
            'catalogReturnPath'
        ));
    }

    public function create()
    {
        $brands = Brand::where('is_active', true)->orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();

        return view('admin.products.create', compact('brands', 'categories'));
    }

    public function store(ProductStoreRequest $request, SaveProductEditor $saveProduct)
    {
        $publicationAction = $request->input('publication_action', Product::PUBLICATION_PUBLISHED);

        try {
            $product = $saveProduct->handle(
                $request->editorData(),
                $request->file('images') ?? [],
                $publicationAction === Product::PUBLICATION_PUBLISHED,
            );

            if ($publicationAction === Product::PUBLICATION_DRAFT) {
                return redirect()->route('admin.products.edit', $product->id)
                    ->with('success', 'Draft berhasil disimpan. Lengkapi data sebelum dipublikasikan.');
            }

            return redirect()->route('admin.products.index')->with('success', 'Product created successfully!');
        } catch (ProductNotReadyForPublication $e) {
            return back()->withErrors($this->publicationErrors($e))->withInput();
        } catch (Throwable $e) {
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
        $categories = Category::active()
            ->orWhere('id', $product->category_id)
            ->orderBy('name')
            ->get();
        $catalogReturnPath = $this->catalogReturnPath($request->query('return_to'));
        $publicationBlockers = $evaluatePublicationReadiness->handle($product);
        $hasLegacyFractionalPrice = $product->variants->where('is_active', true)->pluck('price')
            ->push($product->base_price)->push($product->compare_at_price)
            ->filter(fn ($price) => $price !== null)
            ->contains(fn ($price) => Rupiah::minorUnits($price) % 100 !== 0);

        return view('admin.products.edit', compact(
            'product',
            'brands',
            'categories',
            'catalogReturnPath',
            'publicationBlockers',
            'hasLegacyFractionalPrice'
        ));
    }

    public function update(ProductUpdateRequest $request, $id, SaveProductEditor $saveProduct)
    {
        $product = Product::findOrFail($id);

        try {
            $saveProduct->handle(
                $request->editorData(),
                $request->file('new_images') ?? [],
                $request->input('publication_action') === Product::PUBLICATION_PUBLISHED,
                $product,
            );

            return redirect()->to($this->catalogReturnPath($request->input('return_to')))
                ->with('success', 'Product updated successfully!');
        } catch (ProductNotReadyForPublication $e) {
            return back()->withErrors($this->publicationErrors($e))->withInput();
        } catch (Throwable $e) {
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
        $appIndexPath = route('admin.app-products.index', [], false);
        $shopeeIndexPath = route('admin.shopee-imports.index', [], false);

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
            || ! in_array($parts['path'] ?? '', [$indexPath, $appIndexPath, $shopeeIndexPath], true)) {
            return $indexPath;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        if ($parts['path'] === $shopeeIndexPath) {
            return route('admin.shopee-imports.index', array_filter([
                'batch' => $this->positiveInteger($query['batch'] ?? null),
                'page' => $this->positiveInteger($query['page'] ?? null),
                'filter' => in_array($query['filter'] ?? null, ['work', 'all', 'complete', 'review', 'ready', 'images_failed'], true) ? $query['filter'] : null,
                'search' => is_string($query['search'] ?? null) ? mb_substr(trim($query['search']), 0, 100) : null,
            ]), false);
        }

        if ($parts['path'] === $appIndexPath) {
            return route('admin.app-products.index', array_filter([
                'page' => $this->positiveInteger($query['page'] ?? null),
                'search' => is_string($query['search'] ?? null) ? mb_substr(trim($query['search']), 0, 100) : null,
                'status' => in_array($query['status'] ?? null, ['all', 'draft', 'unlinked', 'hidden', 'price_review'], true) ? $query['status'] : null,
            ]), false);
        }

        return route('admin.products.index', $this->normalizeCatalogContext($query), false);
    }

    private function applyAvailabilityFilter(Builder $query, string $availability): void
    {
        $query->effectiveAvailability($availability);
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
     * @return array<string, array<int, string>>
     */
    private function publicationErrors(ProductNotReadyForPublication $exception): array
    {
        return ['publication' => array_values($exception->blockers())];
    }
}
