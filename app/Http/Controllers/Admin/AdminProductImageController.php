<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\ArchiveProductImage;
use App\Actions\Products\MoveProductImage;
use App\Actions\Products\SetPrimaryProductImage;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProductImageController extends Controller
{
    public function primary(
        Request $request,
        Product $product,
        ProductImage $productImage,
        SetPrimaryProductImage $setPrimaryProductImage
    ): RedirectResponse {
        $setPrimaryProductImage->handle($product, $productImage->getKey());

        return $this->backToEditor($request, $product, 'Foto utama berhasil diperbarui.');
    }

    public function move(
        Request $request,
        Product $product,
        ProductImage $productImage,
        MoveProductImage $moveProductImage
    ): RedirectResponse {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $moveProductImage->handle($product, $productImage->getKey(), $validated['direction']);

        return $this->backToEditor($request, $product, 'Urutan foto berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        Product $product,
        ProductImage $productImage,
        ArchiveProductImage $archiveProductImage
    ): RedirectResponse {
        try {
            $archiveProductImage->handle($product, $productImage->getKey());
        } catch (DomainException $exception) {
            return $this->backToEditor($request, $product, null, $exception->getMessage());
        }

        return $this->backToEditor(
            $request,
            $product,
            'Foto dikeluarkan dari galeri. File tetap disimpan untuk recovery.'
        );
    }

    private function backToEditor(
        Request $request,
        Product $product,
        ?string $success = null,
        ?string $error = null
    ): RedirectResponse {
        $parameters = ['product' => $product->getKey()];
        $returnTo = $request->input('return_to');

        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = mb_substr($returnTo, 0, 2048);
        }

        $response = redirect()->route('admin.products.edit', $parameters);

        if ($success !== null) {
            $response->with('success', $success);
        }

        if ($error !== null) {
            $response->with('error', $error);
        }

        return $response;
    }
}
