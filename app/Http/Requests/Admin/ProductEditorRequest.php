<?php

namespace App\Http\Requests\Admin;

use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ProductEditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function editorData(): array
    {
        // Preserve the existing checkbox convention while all other fields are validated.
        return array_merge($this->validated(), ['is_best_seller' => $this->has('is_best_seller')]);
    }

    protected function editorRules(bool $complete): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => [Rule::requiredIf($complete), 'nullable', 'string', 'max:20000'],
            'compare_at_price' => ['nullable', 'numeric', 'regex:'.Rupiah::WHOLE_PRICE_PATTERN, 'gt:0', 'max:99999999.99'],
            'gender' => [Rule::requiredIf($complete), 'nullable', Rule::in(['Unisex', 'Pria', 'Wanita'])],
            'top_notes' => ['nullable', 'string', 'max:1000'],
            'middle_notes' => ['nullable', 'string', 'max:1000'],
            'base_notes' => ['nullable', 'string', 'max:1000'],
            'variants' => [Rule::requiredIf($complete), 'nullable', 'array', 'max:1'],
            'variants.*.volume' => [Rule::requiredIf($complete), 'nullable', 'required_with:variants.*.price', 'integer', 'min:1', 'max:10000'],
            'variants.*.price' => [Rule::requiredIf($complete), 'nullable', 'required_with:variants.*.volume', 'numeric', 'regex:'.Rupiah::WHOLE_PRICE_PATTERN, 'gt:0', 'max:99999999.99'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

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
            'variants.*.price.regex' => 'Harga jual harus rupiah bulat, tanpa pecahan atau pemisah ribuan.',
            'compare_at_price.regex' => 'Harga coret harus rupiah bulat, tanpa pecahan atau pemisah ribuan.',
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
