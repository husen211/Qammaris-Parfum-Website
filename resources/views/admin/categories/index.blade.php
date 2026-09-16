@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-400">Katalog / Pengelompokan</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Kategori</h1>
            <p class="mt-1 text-sm text-gray-500">Kelola kategori parfum tanpa memutus produk existing.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-black px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">+ Tambah Kategori</a>
    </div>

    <form method="GET" action="{{ route('admin.categories.index') }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row">
        <label for="category-search" class="sr-only">Cari kategori</label>
        <input id="category-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama kategori..." class="min-h-11 flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10">
        <button class="min-h-11 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Cari</button>
        @if($search !== '')
            <a href="{{ route('admin.categories.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Reset</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[minmax(0,1fr)_8rem_8rem_13rem] gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-500 md:grid">
            <span>Kategori</span><span>Produk</span><span>Status</span><span class="text-right">Aksi</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($categories as $category)
                <article class="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_8rem_8rem_13rem] md:items-center">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $category->name }}</p>
                        <p class="mt-1 truncate font-mono text-xs text-gray-400">{{ $category->slug }}</p>
                        @if($category->description)<p class="mt-2 text-sm text-gray-500">{{ \Illuminate\Support\Str::limit($category->description, 100) }}</p>@endif
                    </div>
                    <div><span class="text-xs font-semibold uppercase tracking-wide text-gray-400 md:hidden">Produk </span><span class="text-sm font-semibold text-gray-700">{{ $category->products_count }}</span></div>
                    <div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $category->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2 md:justify-end">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.status', $category) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                            <button class="min-h-10 rounded-lg px-3 py-2 text-sm font-semibold {{ $category->is_active ? 'border border-amber-200 text-amber-700 hover:bg-amber-50' : 'bg-green-700 text-white hover:bg-green-800' }}">{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="px-5 py-14 text-center">
                    <p class="font-semibold text-gray-900">Kategori tidak ditemukan</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $search !== '' ? 'Coba kata pencarian lain.' : 'Tambahkan kategori pertama untuk mulai mengelola katalog.' }}</p>
                </div>
            @endforelse
        </div>
    </div>

    @if($categories->hasPages())
        <div>{{ $categories->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
