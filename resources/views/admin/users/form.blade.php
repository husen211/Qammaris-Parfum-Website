@extends('layouts.admin')
@use('App\Models\User')

@php
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10 sm:text-sm';
    $roles = User::ASSIGNABLE_ROLES;
    if ($user?->role === User::ROLE_LEGACY_ADMIN) {
        $roles[] = User::ROLE_LEGACY_ADMIN;
    }
    $roleHelp = [
        User::ROLE_SUPER_ADMIN => 'Akses penuh: katalog, blog, pesanan, pengguna, persetujuan finansial.',
        User::ROLE_STAFF_ORDER => 'Hanya Pesanan Online: buat pesanan, tandai Lunas, kirim instruksi. Tanpa katalog, blog, atau pengguna.',
        User::ROLE_LEGACY_ADMIN => 'Akun lama sebelum ORD-02. Pindahkan ke Super Admin atau Staff Order.',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <a href="{{ $user ? route('admin.users.show', $user) : route('admin.users.index') }}" class="inline-flex min-h-11 items-center text-sm text-gray-600 hover:text-black">← {{ $user ? $user->name : 'Pengguna & Role' }}</a>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $user ? 'Ubah pengguna' : 'Tambah pengguna' }}</h1>
    </div>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Periksa kembali:</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        @csrf
        @if ($user) @method('PUT') @endif
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Nama</label>
            <input id="name" name="name" value="{{ old('name', $user?->name) }}" required maxlength="100" autocomplete="off" class="{{ $input }}">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input id="username" name="username" value="{{ old('username', $user?->username) }}" maxlength="40" autocapitalize="none" spellcheck="false" autocomplete="off" class="{{ $input }}" aria-describedby="login-help">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" maxlength="255" autocomplete="off" class="{{ $input }}" aria-describedby="login-help">
            </div>
        </div>
        <p id="login-help" class="-mt-2 text-xs text-gray-500">Isi minimal salah satu. Pengguna bisa masuk memakai username atau email.</p>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">Role</legend>
            <div class="mt-2 space-y-2">
                @foreach ($roles as $role)
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-3 has-[:checked]:border-black has-[:checked]:bg-gray-50">
                        <input type="radio" name="role" value="{{ $role }}" @checked(old('role', $user?->role ?? User::ROLE_STAFF_ORDER) === $role) class="mt-1 h-4 w-4">
                        <span><span class="block text-sm font-semibold text-gray-900">{{ User::ROLE_LABELS[$role] }}</span><span class="block text-xs text-gray-500">{{ $roleHelp[$role] }}</span></span>
                    </label>
                @endforeach
            </div>
            @if ($user && $user->is(auth()->user()))
                <p class="mt-2 text-xs text-amber-700">Mengubah role akun Anda sendiri akan mengakhiri sesi Anda. Super Admin terakhir tidak dapat diturunkan.</p>
            @endif
        </fieldset>

        @unless ($user)
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password sementara <span class="font-normal text-gray-500">(opsional)</span></label>
                <input id="password" name="password" type="text" minlength="8" autocomplete="new-password" class="{{ $input }}" aria-describedby="password-help">
                <p id="password-help" class="mt-1 text-xs text-gray-500">Kosongkan agar sistem membuat password acak. Pengguna wajib menggantinya saat pertama masuk.</p>
            </div>
        @endunless

        <div class="flex flex-wrap gap-3">
            <button class="min-h-11 rounded-lg bg-black px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">{{ $user ? 'Simpan perubahan' : 'Buat pengguna' }}</button>
            <a href="{{ $user ? route('admin.users.show', $user) : route('admin.users.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-5 text-sm font-semibold text-gray-700 hover:bg-gray-100">Batal</a>
        </div>
    </form>
</div>
@endsection
