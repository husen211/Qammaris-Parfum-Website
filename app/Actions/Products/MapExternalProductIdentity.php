<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductExternalIdentity;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MapExternalProductIdentity
{
    public function handle(Product $product, mixed $provider, mixed $externalProductId): ProductExternalIdentity
    {
        if (! $product->exists) {
            throw new InvalidArgumentException('Product harus tersimpan sebelum identity eksternal dipetakan.');
        }

        $provider = $this->normalizeProvider($provider);
        $externalProductId = $this->normalizeExternalProductId($externalProductId);

        return DB::transaction(function () use ($product, $provider, $externalProductId) {
            $identityForCode = ProductExternalIdentity::query()
                ->where('provider', $provider)
                ->where('external_product_id', $externalProductId)
                ->lockForUpdate()
                ->first();

            if ($identityForCode) {
                if ($identityForCode->product_id === $product->getKey()) {
                    return $identityForCode;
                }

                throw new DomainException('Kode produk provider sudah terhubung ke product lain.');
            }

            $identityForProduct = $product->externalIdentities()
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first();

            if ($identityForProduct) {
                throw new DomainException('Product sudah mempunyai identity untuk provider ini dan tidak dapat di-rebind.');
            }

            return $product->externalIdentities()->create([
                'provider' => $provider,
                'external_product_id' => $externalProductId,
            ]);
        });
    }

    private function normalizeProvider(mixed $provider): string
    {
        if (! is_string($provider)) {
            throw new InvalidArgumentException('Provider identity eksternal harus berupa teks.');
        }

        $provider = strtolower(trim($provider));

        if (! in_array($provider, ProductExternalIdentity::SUPPORTED_PROVIDERS, true)) {
            throw new InvalidArgumentException('Provider identity eksternal tidak didukung.');
        }

        return $provider;
    }

    private function normalizeExternalProductId(mixed $externalProductId): string
    {
        if (! is_string($externalProductId) && ! is_int($externalProductId)) {
            throw new InvalidArgumentException('Kode produk provider harus berupa teks atau integer.');
        }

        $externalProductId = trim((string) $externalProductId);

        if ($externalProductId === '' || mb_strlen($externalProductId) > 191) {
            throw new InvalidArgumentException('Kode produk provider wajib diisi dan maksimal 191 karakter.');
        }

        return $externalProductId;
    }
}
