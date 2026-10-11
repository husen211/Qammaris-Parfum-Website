<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BlogPostRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('blog.manage');
    }

    public function rules(): array
    {
        return ['revision' => ['required', 'integer', 'min:1', 'max:'.PHP_INT_MAX]];
    }
}
