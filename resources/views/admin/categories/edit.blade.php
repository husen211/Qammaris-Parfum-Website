@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-gray-500 hover:text-black">← Kembali ke Kategori</a>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">Edit Kategori</h1>
        <p class="mt-1 text-sm text-gray-500">Dipakai oleh {{ $category->products_count }} produk · Slug: <span class="font-mono">{{ $category->slug }}</span></p>
    </div>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @method('PUT')
        @include('admin.categories._form', ['submitLabel' => 'Simpan Perubahan'])
    </form>
</div>
@endsection
