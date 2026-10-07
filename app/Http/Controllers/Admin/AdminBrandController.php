<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandStoreRequest;
use App\Http\Requests\Admin\BrandUpdateRequest;
use App\Models\Brand;
use App\Support\SearchMatcher;
use Illuminate\Http\Request;

class AdminBrandController extends Controller
{
    public function index(Request $request)
    {
        $search = SearchMatcher::term($request->query('search'));

        $query = Brand::query()->withCount('products');
        if ($search !== '') {
            SearchMatcher::constrain($query, $search, (clone $query)->select(['id', 'name'])->get(), fn ($row) => [$row->name]);
        }
        $brands = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.brands.index', compact('brands', 'search'));
    }

    public function create()
    {
        return view('admin.brands.create');
    }

    public function store(BrandStoreRequest $request)
    {
        Brand::create($request->validated());

        return redirect()->route('admin.brands.index')->with('success', 'Brand berhasil dibuat.');
    }

    public function edit(Brand $brand)
    {
        $brand->loadCount('products');

        return view('admin.brands.edit', compact('brand'));
    }

    public function update(BrandUpdateRequest $request, Brand $brand)
    {
        $brand->update($request->validated());

        return redirect()->route('admin.brands.index')->with('success', 'Brand berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Brand $brand)
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $brand->update($validated);

        $message = $brand->is_active ? 'Brand berhasil diaktifkan.' : 'Brand berhasil dinonaktifkan.';

        return back()->with('success', $message);
    }
}
