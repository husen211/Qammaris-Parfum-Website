<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends ProductEditorRequest
{
    public function rules(): array
    {
        $publishing = $this->input('publication_action', Product::PUBLICATION_PUBLISHED)
            === Product::PUBLICATION_PUBLISHED;

        return $this->editorRules($publishing) + [
            'publication_action' => ['sometimes', Rule::in([
                Product::PUBLICATION_DRAFT,
                Product::PUBLICATION_PUBLISHED,
            ])],
            'brand_id' => [
                Rule::requiredIf($publishing),
                'nullable',
                Rule::exists('brands', 'id')->where('is_active', true),
            ],
            'category_id' => [
                Rule::requiredIf($publishing),
                'nullable',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'variants.*.sku' => ['nullable', 'string', 'max:255', 'distinct', 'unique:product_variants,sku'],
            'images' => [Rule::requiredIf($publishing), 'nullable', 'array', 'min:1', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'images.required' => 'Tambahkan foto utama sebelum mempublikasikan produk.',
        ];
    }
}
