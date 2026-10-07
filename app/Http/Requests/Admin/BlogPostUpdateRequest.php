<?php

namespace App\Http\Requests\Admin;

use App\Services\BlogMediaStorage;
use App\Support\BlogEditorialRules;
use Illuminate\Foundation\Http\FormRequest;

class BlogPostUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->boolean('tags_present')) {
            $this->merge(['tag_ids' => $this->input('tag_ids', [])]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return BlogEditorialRules::fields() + [
            'revision' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'featured_image' => ['nullable', ...array_slice(BlogMediaStorage::UPLOAD_RULES, 1)],
        ];
    }
}
