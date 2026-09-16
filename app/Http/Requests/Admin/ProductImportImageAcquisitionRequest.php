<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductImportImageAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'confirm_image_acquisition' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_image_acquisition.accepted' => 'Konfirmasi akuisisi gambar wajib dicentang.',
        ];
    }
}
