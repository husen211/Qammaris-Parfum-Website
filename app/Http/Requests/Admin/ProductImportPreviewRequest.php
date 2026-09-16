<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_file.required' => 'Pilih file CSV Qammaris yang akan dipreview.',
            'product_file.file' => 'Upload harus berupa file.',
            'product_file.mimes' => 'Format file harus CSV.',
            'product_file.max' => 'Ukuran file maksimum 5 MB.',
        ];
    }
}
