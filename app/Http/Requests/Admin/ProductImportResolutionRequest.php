<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductImportResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'distinct', Rule::in([
                'name', 'description', 'brand', 'category', 'gender',
                'is_best_seller', 'stock_quantity', 'fragrance_notes', 'offer',
            ])],
            'confirm_resolution' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.required' => 'Pilih minimal satu field yang ingin diterapkan.',
            'fields.min' => 'Pilih minimal satu field yang ingin diterapkan.',
            'fields.*.in' => 'Pilihan field resolusi tidak valid.',
            'confirm_resolution.accepted' => 'Konfirmasi resolusi wajib dicentang.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.product-imports.create', [
            'batch' => $this->route('productImportBatch')->getKey(),
        ]).'#protected-resolution-'.$this->route('productImportRow')->getKey();
    }
}
