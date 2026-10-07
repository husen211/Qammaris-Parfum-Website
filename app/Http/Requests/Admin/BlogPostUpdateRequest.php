<?php

namespace App\Http\Requests\Admin;

use App\Models\BlogPost;
use App\Services\BlogMediaStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'category' => ['required', Rule::in(BlogPost::CATEGORY_OPTIONS)],
            'author' => ['nullable', 'string', 'max:100'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'featured_image' => ['nullable', ...array_slice(BlogMediaStorage::UPLOAD_RULES, 1)],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
