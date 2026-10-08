@extends('layouts.admin')

@php
    $card = 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm';
    $time = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i') : '—';
    $self = $user->is(auth()->user());
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← Pengguna &amp; Role</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $user->name }}</h1>
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-800">{{ $user->roleLabel() }}</span>
        </div>
    </div>

    @if (session('temporary_password'))
        <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-5">
            <p class="text-sm font-semibold text-amber-900">Password sementara — hanya ditampilkan sekali</p>
            <p class="mt-2 select-all break-all rounded-lg bg-white px-3 py-2 font-mono text-lg text-gray-900">{{ session('temporary_password') }}</p>
            <p class="mt-2 text-xs text-amber-900">Berikan langsung ke pengguna (jangan di grup). Pengguna wajib menggantinya saat masuk.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="{{ $card }} space-y-3" aria-labelledby="profile-title">
            <h2 id="profile-title" class="text-lg font-semibold text-gray-900">Akun</h2>
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Username</dt><dd class="font-medium text-gray-900">{{ $user->username ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Email</dt><dd class="break-all font-medium text-gray-900">{{ $user->email ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Terakhir masuk</dt><dd class="font-medium text-gray-900">{{ $time($user->last_login_at) }}</dd></div>
                <div><dt class="text-gray-500">Dibuat</dt><dd class="font-medium text-gray-900">{{ $time($user->created_at) }}{{ $user->created_by_id ? ' oleh '.($names[$user->created_by_id] ?? '#'.$user->created_by_id) : '' }}</dd></div>
                <div><dt class="text-gray-500">Terakhir diubah</dt><dd class="font-medium text-gray-900">{{ $time($user->updated_at) }}{{ $user->updated_by_id ? ' oleh '.($names[$user->updated_by_id] ?? '#'.$user->updated_by_id) : '' }}</dd></div>
                <div><dt class="text-gray-500">Password</dt><dd class="font-medium text-gray-900">{{ $user->must_change_password ? 'Sementara (wajib diganti)' : 'Sudah diatur pengguna' }}</dd></div>
            </dl>
            <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex min-h-11 items-center rounded-lg bg-black px-5 text-sm font-semibold text-white hover:bg-gray-800">Ubah nama / login / role</a>
        </section>

        <section class="{{ $card }} space-y-4" aria-labelledby="access-title">
            <h2 id="access-title" class="text-lg font-semibold text-gray-900">Akses</h2>
            <form method="POST" action="{{ route('admin.users.status', $user) }}" class="space-y-2">
                @csrf @method('PATCH')
                <input type="hidden" name="active" value="{{ $user->is_active ? 0 : 1 }}">
                @if ($user->is_active)
                    <label for="note" class="block text-sm font-medium text-gray-700">Alasan menonaktifkan <span class="font-normal text-gray-500">(opsional)</span></label>
                    <input id="note" name="note" maxlength="200" class="min-h-11 w-full rounded-lg border border-gray-300 px-3 text-base sm:text-sm">
                    <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-red-300 px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Nonaktifkan akun</button>
                    <p class="text-xs text-gray-500">Semua sesi akun ini langsung berakhir.{{ $self ? ' Termasuk sesi Anda sendiri.' : '' }}</p>
                @else
                    <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-green-700 px-4 text-sm font-semibold text-white hover:bg-green-800">Aktifkan akun</button>
                @endif
            </form>
            <form method="POST" action="{{ route('admin.users.password-reset', $user) }}" class="border-t border-gray-100 pt-4">
                @csrf
                <button class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50">Reset password</button>
                <p class="mt-1 text-xs text-gray-500">Membuat password sementara baru dan mengakhiri semua sesi akun ini.</p>
            </form>
        </section>
    </div>

    <section class="{{ $card }}" aria-labelledby="changes-title">
        <h2 id="changes-title" class="text-lg font-semibold text-gray-900">Riwayat akun</h2>
        @include('admin.users._changes', ['changes' => $changes, 'names' => $names])
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="orders-title">
            <h2 id="orders-title" class="text-lg font-semibold text-gray-900">Aktivitas pesanan terbaru</h2>
            @forelse ($orderEvents as $event)
                <p class="mt-2 text-sm text-gray-700"><span class="tabular-nums text-gray-500">{{ $time($event->created_at) }}</span> · <a href="{{ route('admin.orders.show', $event->order_id) }}" class="font-medium underline underline-offset-2">{{ $event->code }}</a> · {{ $event->kind }}{{ $event->stage ? ' ('.$event->stage.')' : '' }}</p>
            @empty
                <p class="mt-3 text-sm text-gray-500">Belum ada aktivitas pesanan.</p>
            @endforelse
        </section>
        <section class="{{ $card }}" aria-labelledby="products-title">
            <h2 id="products-title" class="text-lg font-semibold text-gray-900">Perubahan produk terbaru</h2>
            @forelse ($productChanges as $change)
                <p class="mt-2 text-sm text-gray-700"><span class="tabular-nums text-gray-500">{{ $time($change->created_at) }}</span> · {{ $change->name ?? '#'.$change->product_id }} · {{ $change->action }}</p>
            @empty
                <p class="mt-3 text-sm text-gray-500">Belum ada perubahan produk.</p>
            @endforelse
        </section>
    </div>
</div>
@endsection
