@extends('layouts.admin')

@section('content')
<div class="flex flex-col gap-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Blog Posts</h1>
            <p class="text-sm text-gray-500">Kelola artikel jurnal dan konten blog.</p>
        </div>
        <a href="{{ route('admin.blog-posts.create') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-brand-black hover:bg-brand-emerald transition-colors rounded">
            + Buat Blog Baru
        </a>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-lg p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari judul atau ringkasan..."
                class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
        </div>
        <div>
            <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Category</label>
            <select name="category" class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                <option value="">Semua</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-semibold uppercase tracking-widest text-gray-400">Status</label>
            <select name="status" class="mt-2 w-full border border-gray-200 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-gold/40">
                <option value="">Semua</option>
                <option value="published" @selected(request('status') === 'published')>Published</option>
                <option value="scheduled" @selected(request('status') === 'scheduled')>Scheduled</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
        </div>
        <div class="md:col-span-4 flex items-center justify-end gap-3">
            <a href="{{ route('admin.blog-posts.index') }}" class="text-sm text-gray-500 hover:text-brand-black">Reset</a>
            <button type="submit" class="px-4 py-2 bg-brand-black text-white text-sm font-semibold rounded hover:bg-brand-emerald transition-colors">
                Terapkan Filter
            </button>
        </div>
    </form>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-widest text-gray-400">
                <tr>
                    <th class="px-4 py-3 text-left">Post</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Published</th>
                    <th class="px-4 py-3 text-left">Views</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    @php
                        $isPublished = $post->is_published && $post->published_at && $post->published_at->isPast();
                        $isScheduled = $post->is_published && $post->published_at && $post->published_at->isFuture();
                    @endphp
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-16 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $post->title }}</p>
                                    <p class="text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($post->excerpt, 80) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-gray-600">{{ $post->category }}</td>
                        <td class="px-4 py-4">
                            @if($isPublished)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold bg-green-50 text-green-700 rounded">Published</span>
                            @elseif($isScheduled)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold bg-amber-50 text-amber-700 rounded">Scheduled</span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 text-xs font-semibold bg-gray-100 text-gray-600 rounded">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-gray-600">
                            {{ $post->published_at ? $post->published_at->format('d M Y') : '-' }}
                        </td>
                        <td class="px-4 py-4 text-gray-600">{{ $post->view_count }}</td>
                        <td class="px-4 py-4">
                            <div class="flex justify-end items-center gap-2">
                                <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="px-3 py-1 text-xs border border-gray-200 rounded hover:border-brand-black hover:text-brand-black">View</a>
                                <a href="{{ route('admin.blog-posts.edit', $post) }}" class="px-3 py-1 text-xs border border-gray-200 rounded hover:border-brand-black hover:text-brand-black">Edit</a>
                                <form method="POST" action="{{ route('admin.blog-posts.destroy', $post) }}" onsubmit="return confirm('Hapus blog ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1 text-xs border border-red-200 text-red-600 rounded hover:bg-red-50">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                            Belum ada blog post. Mulai dengan membuat artikel baru.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">
        {{ $posts->links('pagination::tailwind') }}
    </div>
</div>
@endsection
