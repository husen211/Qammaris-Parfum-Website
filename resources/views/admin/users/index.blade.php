@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-400">Pengaturan</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Pengguna &amp; Role</h1>
            <p class="mt-1 text-sm text-gray-500">Akun admin website. Super Admin mengelola akun; Staff Order hanya mengakses Pesanan Online.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-black px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">+ Tambah pengguna</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row">
        <label for="user-search" class="sr-only">Cari pengguna</label>
        <input id="user-search" name="search" type="search" value="{{ $search }}" placeholder="Nama, email, atau username…" class="min-h-11 flex-1 rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10 sm:text-sm">
        <button class="min-h-11 rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black">Cari</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[minmax(0,1.4fr)_10rem_8rem_11rem] gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-500 md:grid">
            <span>Pengguna</span><span>Role</span><span>Status</span><span class="text-right">Terakhir masuk</span>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse ($users as $user)
                <a href="{{ route('admin.users.show', $user) }}" class="grid gap-2 p-5 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none md:grid-cols-[minmax(0,1.4fr)_10rem_8rem_11rem] md:items-center md:gap-4">
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-gray-900">{{ $user->name }}@if ($user->is(auth()->user())) <span class="font-normal text-gray-500">(Anda)</span>@endif</span>
                        <span class="block truncate text-sm text-gray-500">{{ $user->username ? '@'.$user->username : '' }}{{ $user->username && $user->email ? ' · ' : '' }}{{ $user->email }}</span>
                    </span>
                    <span class="text-sm font-medium text-gray-800">{{ $user->roleLabel() }}</span>
                    <span><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></span>
                    <span class="text-xs text-gray-500 md:text-right">{{ $user->last_login_at?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i') ?? 'Belum pernah' }}</span>
                </a>
            @empty
                <div class="px-5 py-14 text-center">
                    <p class="font-semibold text-gray-900">Pengguna tidak ditemukan</p>
                    <p class="mt-1 text-sm text-gray-500">Coba kata pencarian lain.</p>
                </div>
            @endforelse
        </div>
    </div>
    @if ($users->hasPages())<div>{{ $users->links('pagination::tailwind') }}</div>@endif

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="recent-title">
        <h2 id="recent-title" class="text-lg font-semibold text-gray-900">Perubahan akun terbaru</h2>
        @include('admin.users._changes', ['changes' => $recent, 'names' => $names])
    </section>
</div>
@endsection
