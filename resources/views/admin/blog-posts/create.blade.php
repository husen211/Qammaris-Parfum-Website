@extends('layouts.admin')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Buat Blog Post</h1>
        <p class="text-sm text-gray-500">Tulis artikel baru untuk jurnal Qammaris.</p>
    </div>

    <form method="POST" action="{{ route('admin.blog-posts.store') }}" enctype="multipart/form-data" class="space-y-6 bg-white border border-gray-200 rounded-lg p-6">
        @csrf

        <div>
            <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Judul</label>
            <input type="text" name="title" value="{{ old('title') }}" required
                class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
            @error('title')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Excerpt</label>
            <textarea name="excerpt" rows="3" required
                class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">{{ old('excerpt') }}</textarea>
            @error('excerpt')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Content</label>
            <textarea name="content" rows="12" required
                class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-brand-gold/40">{{ old('content') }}</textarea>
            <p class="text-xs text-gray-400 mt-2">Gunakan HTML sederhana jika perlu (mis. &lt;p&gt;, &lt;strong&gt;, &lt;h2&gt;).</p>
            @error('content')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Category</label>
                <select name="category" required class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Author</label>
                <input type="text" name="author" value="{{ old('author', auth()->user()->name ?? 'Admin') }}"
                    class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Meta Description</label>
                <input type="text" name="meta_description" value="{{ old('meta_description') }}"
                    class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                <p class="text-xs text-gray-400 mt-2">Kosongkan untuk memakai excerpt.</p>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Featured Image</label>
                <input type="file" name="featured_image" accept="image/*"
                    class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm">
                @error('featured_image')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published'))
                    class="h-4 w-4 text-brand-gold border-gray-300 rounded">
                <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Publish</label>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Publish Date</label>
                <input type="datetime-local" name="published_at" value="{{ old('published_at') }}"
                    class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                <p class="text-xs text-gray-400 mt-2">Kosongkan untuk publish sekarang.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.blog-posts.index') }}" class="text-sm text-gray-500 hover:text-brand-black">Batal</a>
            <button type="submit" class="px-6 py-3 bg-brand-black text-white text-sm font-semibold rounded hover:bg-brand-emerald transition-colors">
                Simpan Blog
            </button>
        </div>
    </form>
</div>
@endsection
