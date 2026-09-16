<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->getKey() : $product;
        $currentProduct = $product instanceof Product ? $product : Product::find($productId);
        $currentBrandId = $currentProduct?->brand_id;
        $currentCategoryId = $currentProduct?->category_id;
        $offerId = $this->input('variants.0.id');
        $publishing = $this->input('publication_action') === Product::PUBLICATION_PUBLISHED;
        $requiresCompleteProduct = $publishing || $currentProduct?->isPublished();

        return [
            'publication_action' => ['sometimes', Rule::in(['save', Product::PUBLICATION_PUBLISHED])],
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => [
                Rule::requiredIf($requiresCompleteProduct),
                'nullable',
                Rule::exists('brands', 'id')->where(function ($query) use ($currentBrandId, $publishing): void {
                    $query->where('is_active', true);

                    if (! $publishing && $currentBrandId) {
                        $query->orWhere('id', $currentBrandId);
                    }
                }),
            ],
            'category_id' => [
                Rule::requiredIf($requiresCompleteProduct),
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) use ($currentCategoryId, $publishing): void {
                    $query->where('is_active', true);

                    if (! $publishing && $currentCategoryId) {
                        $query->orWhere('id', $currentCategoryId);
                    }
                }),
            ],
            'description' => [Rule::requiredIf($requiresCompleteProduct), 'nullable', 'string', 'max:20000'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'gender' => [Rule::requiredIf($requiresCompleteProduct), 'nullable', Rule::in(['Unisex', 'Pria', 'Wanita'])],
            'availability_status' => [
                'sometimes',
                'required',
                Rule::in([
                    Product::AVAILABILITY_UNKNOWN,
                    Product::AVAILABILITY_AVAILABLE,
                    Product::AVAILABILITY_SOLD_OUT,
                ]),
            ],
            'availability_confirmed' => ['sometimes', 'boolean'],
            'top_notes' => ['nullable', 'string', 'max:1000'],
            'middle_notes' => ['nullable', 'string', 'max:1000'],
            'base_notes' => ['nullable', 'string', 'max:1000'],
            'variants' => [Rule::requiredIf($requiresCompleteProduct), 'nullable', 'array', 'max:1'],
            'variants.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')
                    ->where(fn ($query) => $query->where('product_id', $productId)),
            ],
            'variants.*.volume' => [Rule::requiredIf($requiresCompleteProduct), 'nullable', 'required_with:variants.*.price', 'integer', 'min:1', 'max:10000'],
            'variants.*.price' => [Rule::requiredIf($requiresCompleteProduct), 'nullable', 'required_with:variants.*.volume', 'numeric', 'gt:0', 'max:99999999.99'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'variants.*.sku' => [
                'nullable',
                'string',
                'max:255',
                'distinct',
                Rule::unique('product_variants', 'sku')->ignore($offerId),
            ],
            'new_images' => ['nullable', 'array', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'new_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brand_id.required' => 'Pilih brand sebelum mempublikasikan produk.',
            'category_id.required' => 'Pilih kategori sebelum mempublikasikan produk.',
            'description.required' => 'Isi deskripsi sebelum mempublikasikan produk.',
            'gender.required' => 'Pilih gender/audience sebelum mempublikasikan produk.',
            'variants.required' => 'Isi satu ukuran dan harga sebelum mempublikasikan produk.',
            'variants.*.volume.required' => 'Isi ukuran produk sebelum mempublikasikan produk.',
            'variants.*.price.required' => 'Isi harga jual sebelum mempublikasikan produk.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCompareAtPrice($validator);
                $this->validateImageLimit($validator);
                $this->validatePrimaryImageForPublish($validator);
            },
        ];
    }

    private function validateCompareAtPrice(Validator $validator): void
    {
        if (! $this->filled('compare_at_price')) {
            return;
        }

        $prices = collect($this->input('variants', []))
            ->pluck('price')
            ->filter(fn ($price) => is_numeric($price) && (float) $price > 0);

        if ($prices->isEmpty()) {
            $validator->errors()->add(
                'compare_at_price',
                'Isi harga jual sebelum menambahkan harga coret.'
            );

            return;
        }

        if ((float) $this->input('compare_at_price') <= (float) $prices->min()) {
            $validator->errors()->add(
                'compare_at_price',
                'Harga coret harus lebih besar dari harga jual terendah.'
            );
        }
    }

    private function validateImageLimit(Validator $validator): void
    {
        $newImageCount = count($this->file('new_images', []));

        if ($newImageCount === 0) {
            return;
        }

        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->getKey() : $product;
        $existingImageCount = Product::whereKey($productId)->first()?->images()->count() ?? 0;

        if ($existingImageCount + $newImageCount > ProductImage::MAX_PER_PRODUCT) {
            $validator->errors()->add(
                'new_images',
                'Produk hanya boleh memiliki maksimum tiga gambar.'
            );
        }
    }

    private function validatePrimaryImageForPublish(Validator $validator): void
    {
        if ($this->input('publication_action') !== Product::PUBLICATION_PUBLISHED) {
            return;
        }

        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->getKey() : $product;
        $hasPrimaryImage = Product::whereKey($productId)
            ->whereHas('primaryImage')
            ->exists();

        if (! $hasPrimaryImage && count($this->file('new_images', [])) === 0) {
            $validator->errors()->add(
                'new_images',
                'Tambahkan foto utama sebelum mempublikasikan produk.'
            );
        }
    }
}
