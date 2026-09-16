<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
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
        $publishing = $this->input('publication_action', Product::PUBLICATION_PUBLISHED)
            === Product::PUBLICATION_PUBLISHED;

        return [
            'publication_action' => ['sometimes', Rule::in([
                Product::PUBLICATION_DRAFT,
                Product::PUBLICATION_PUBLISHED,
            ])],
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => [
                Rule::requiredIf($publishing),
                'nullable',
                Rule::exists('brands', 'id')->where('is_active', true),
            ],
            'category_id' => [Rule::requiredIf($publishing), 'nullable', 'exists:categories,id'],
            'description' => [Rule::requiredIf($publishing), 'nullable', 'string', 'max:20000'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'gender' => [Rule::requiredIf($publishing), 'nullable', Rule::in(['Unisex', 'Pria', 'Wanita'])],
            'top_notes' => ['nullable', 'string', 'max:1000'],
            'middle_notes' => ['nullable', 'string', 'max:1000'],
            'base_notes' => ['nullable', 'string', 'max:1000'],
            'variants' => [Rule::requiredIf($publishing), 'nullable', 'array', 'max:1'],
            'variants.*.volume' => [Rule::requiredIf($publishing), 'nullable', 'required_with:variants.*.price', 'integer', 'min:1', 'max:10000'],
            'variants.*.price' => [Rule::requiredIf($publishing), 'nullable', 'required_with:variants.*.volume', 'numeric', 'gt:0', 'max:99999999.99'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'variants.*.sku' => ['nullable', 'string', 'max:255', 'distinct', 'unique:product_variants,sku'],
            'images' => [Rule::requiredIf($publishing), 'nullable', 'array', 'min:1', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
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
            'images.required' => 'Tambahkan foto utama sebelum mempublikasikan produk.',
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
}
