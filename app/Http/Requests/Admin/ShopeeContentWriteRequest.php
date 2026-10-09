<?php

namespace App\Http\Requests\Admin;

use App\Services\ShopeeContentPreviewer;
use Illuminate\Foundation\Http\FormRequest;

class ShopeeContentWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('productImportBatch');

        return (bool) $this->user()?->can('catalog.manage') && $batch?->contract_version === ShopeeContentPreviewer::VERSION
            && $batch->actor_id === $this->user()->id;
    }

    public function rules(): array
    {
        if ($this->routeIs('admin.shopee-imports.choose')) {
            return ['product_id' => ['required', 'integer', 'min:1'], 'replace_description' => ['sometimes', 'boolean'],
                'confirm_size_mismatch' => ['sometimes', 'boolean'],
                'confirmed_website_ml' => ['required_if:confirm_size_mismatch,1', 'nullable', 'integer', 'min:1', 'max:10000']];
        }
        $rules = ['confirm' => ['accepted']];
        if ($this->routeIs('admin.shopee-imports.publish')) {
            $rules += ['rows' => ['required', 'array', 'min:1', 'max:1000'], 'rows.*' => ['integer', 'min:1', 'distinct']];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['confirm.accepted' => 'Centang konfirmasi sebelum melanjutkan.', 'rows.required' => 'Pilih produk yang akan diterbitkan.'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.shopee-imports.index', ['batch' => $this->route('productImportBatch')->id] + $this->only(['filter', 'search', 'page']));
    }
}
