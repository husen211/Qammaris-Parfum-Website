<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShopeeContentPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('catalog.manage');
    }

    public function rules(): array
    {
        return ['basic_file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx,zip', 'max:8192'],
            'media_file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx,zip', 'max:8192']];
    }

    public function attributes(): array
    {
        return ['basic_file' => 'file Informasi Dasar', 'media_file' => 'file Media'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.shopee-imports.index');
    }
}
