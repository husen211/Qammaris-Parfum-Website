<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => [
                'required',
                Rule::exists('brands', 'id')->where('is_active', true),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string', 'max:20000'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'gender' => ['required', Rule::in(['Unisex', 'Pria', 'Wanita'])],
            'top_notes' => ['nullable', 'string', 'max:1000'],
            'middle_notes' => ['nullable', 'string', 'max:1000'],
            'base_notes' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'size:1'],
            'variants.*.volume' => ['required', 'integer', 'min:1', 'max:10000'],
            'variants.*.price' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'variants.*.sku' => ['nullable', 'string', 'max:255', 'distinct', 'unique:product_variants,sku'],
            'images' => ['required', 'array', 'min:1', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCompareAtPrice($validator);
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
}
