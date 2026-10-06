<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductMaintenanceApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'confirm_apply' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm_apply.accepted' => 'Konfirmasi apply maintenance wajib dicentang.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.product-maintenance.create', [
            'batch' => $this->route('productImportBatch')->getKey(),
        ]);
    }
}
