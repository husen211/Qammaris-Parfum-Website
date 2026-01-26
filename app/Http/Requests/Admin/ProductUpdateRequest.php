<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand_id' => ['required', 'exists:brands,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'gender' => ['nullable', 'string'],
            'top_notes' => ['nullable', 'string'],
            'middle_notes' => ['nullable', 'string'],
            'base_notes' => ['nullable', 'string'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.volume' => ['required', 'integer'],
            'variants.*.price' => ['required', 'numeric'],
            'variants.*.stock' => ['required', 'integer'],
            'new_images' => ['nullable', 'array'],
            'new_images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
