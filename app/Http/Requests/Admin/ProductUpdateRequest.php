<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
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
        $offerId = $this->input('variants.0.id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => [
                'required',
                Rule::exists('brands', 'id')->where(function ($query) use ($currentBrandId): void {
                    $query->where('is_active', true);

                    if ($currentBrandId) {
                        $query->orWhere('id', $currentBrandId);
                    }
                }),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:20000'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'gender' => ['required', Rule::in(['Unisex', 'Pria', 'Wanita'])],
            'top_notes' => ['nullable', 'string', 'max:1000'],
            'middle_notes' => ['nullable', 'string', 'max:1000'],
            'base_notes' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'size:1'],
            'variants.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')
                    ->where(fn ($query) => $query->where('product_id', $productId)),
            ],
            'variants.*.volume' => ['required', 'integer', 'min:1', 'max:10000'],
            'variants.*.price' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'variants.*.sku' => [
                'nullable',
                'string',
                'max:255',
                'distinct',
                Rule::unique('product_variants', 'sku')->ignore($offerId),
            ],
            'new_images' => ['nullable', 'array', 'max:3'],
            'new_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCompareAtPrice($validator);
                $this->validateImageLimit($validator);
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

        if ($prices->isNotEmpty() && (float) $this->input('compare_at_price') <= (float) $prices->min()) {
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

        if ($existingImageCount + $newImageCount > 3) {
            $validator->errors()->add(
                'new_images',
                'Produk hanya boleh memiliki maksimum tiga gambar.'
            );
        }
    }
}
