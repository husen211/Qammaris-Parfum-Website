<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductMaintenancePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'maintenance_file.required' => 'Pilih file CSV maintenance yang akan dipreview.',
            'maintenance_file.file' => 'Upload harus berupa file.',
            'maintenance_file.mimes' => 'Format file harus CSV.',
            'maintenance_file.max' => 'Ukuran file maksimum 5 MB.',
        ];
    }
}
