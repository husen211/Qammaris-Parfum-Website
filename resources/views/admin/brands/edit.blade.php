@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <a href="{{ route('admin.brands.index') }}" class="text-sm font-medium text-gray-500 hover:text-black">← Kembali ke Brand</a>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">Edit Brand</h1>
        <p class="mt-1 text-sm text-gray-500">Dipakai oleh {{ $brand->products_count }} produk · Slug: <span class="font-mono">{{ $brand->slug }}</span></p>
    </div>

    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @method('PUT')
        @include('admin.brands._form', ['submitLabel' => 'Simpan Perubahan'])
    </form>
</div>
@endsection
