@extends('layouts.admin')

@php $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10'; @endphp

@section('content')
<div class="mx-auto max-w-md space-y-6">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Ganti password</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $user->name }} · {{ $user->roleLabel() }}</p>
    </div>
    @if ($user->must_change_password)
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Anda memakai password sementara. Ganti dulu sebelum melanjutkan.</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ route('admin.account.password.update') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <div>
            <label for="current_password" class="block text-sm font-medium text-gray-700">Password saat ini</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="{{ $input }}">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password baru <span class="font-normal text-gray-500">(min. 8 karakter)</span></label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="{{ $input }}">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Ulangi password baru</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="{{ $input }}">
        </div>
        <button class="min-h-11 w-full rounded-lg bg-black px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">Simpan password baru</button>
        <p class="text-xs text-gray-500">Sesi lain akun ini akan berakhir; sesi di perangkat ini tetap aktif.</p>
    </form>
</div>
@endsection
