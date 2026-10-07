<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminBlogTaxonomyController extends Controller
{
    public function index()
    {
        return view('admin.blog-posts.taxonomy', [
            'categories' => BlogCategory::orderBy('name')->get(),
            'tags' => BlogTag::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['category', 'tag'])], 'name' => ['required', 'string', 'max:100']]);
        $model = $data['kind'] === 'category' ? BlogCategory::class : BlogTag::class;
        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '' || $model::where('name', $name)->orWhere('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Nama sudah dipakai atau tidak mempunyai huruf/angka yang valid.']);
        }
        $model::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);

        return back()->with('success', 'Kategori/tag ditambahkan.');
    }

    public function status(Request $request, string $kind, int $id)
    {
        abort_unless(in_array($kind, ['category', 'tag'], true), 404);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $model = $kind === 'category' ? BlogCategory::class : BlogTag::class;
        $model::findOrFail($id)->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'Status diperbarui. Artikel dan URL tetap disimpan.');
    }
}
