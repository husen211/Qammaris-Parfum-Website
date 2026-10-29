<?php

namespace App\Http\Controllers\Integrations;

use App\Exceptions\OrderApi\OrderApiException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductExternalIdentity;
use App\Support\OrderApi\OrderApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WAIT-02: public product page URLs for App product ids, so the App Daftar Tunggu message links to the product.
 * Read-only and outside the frozen Order API OpenAPI baseline; ids that no customer can open are left out.
 */
class ProductLinkApiController extends Controller
{
    private const MAX_IDS = 50;

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->query('app_product_ids');
        $ids = is_string($raw) && $raw !== '' ? explode(',', $raw) : [];
        if ($ids === [] || count($ids) > self::MAX_IDS
            || array_filter($ids, fn ($id) => preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id) !== 1) !== []) {
            throw new OrderApiException(422, 'validation_failed', 'Parameter tidak valid.', [
                'fields' => ['app_product_ids' => '1–50 id dipisah koma, masing-masing 1–128 karakter (huruf, angka, - atau _)'],
            ]);
        }
        $ids = array_values(array_unique($ids));

        $links = ProductExternalIdentity::query()
            ->where('provider', ProductExternalIdentity::PROVIDER_QAMMARIS_APP)
            ->whereIn('external_product_id', $ids)
            ->whereHas('product', fn ($query) => $query->published())
            ->with('product:id,slug')
            ->get()
            ->mapWithKeys(fn ($identity) => [$identity->external_product_id => route('products.show', $identity->product->slug)]);

        return OrderApiResponse::json($request, ['data' => collect($ids)
            ->filter(fn ($id) => $links->has($id))
            ->map(fn ($id) => ['app_product_id' => $id, 'url' => $links[$id]])
            ->values()->all()]);
    }
}
