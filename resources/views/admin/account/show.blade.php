@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-md space-y-6">
    <div>
        <p class="text-sm font-medium text-gray-400">Akun</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">{{ $user->name }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $user->roleLabel() }} · {{ $user->loginIdentifier() }}</p>
    </div>

    <dl class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white text-sm shadow-sm">
        <div class="flex justify-between gap-4 px-5 py-4">
            <dt class="text-gray-500">Masuk terakhir</dt>
            <dd class="text-right font-medium text-gray-900">{{ $user->last_login_at?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i') ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 px-5 py-4">
            <dt class="text-gray-500">Sesi berakhir bila tidak dipakai</dt>
            <dd class="text-right font-medium text-gray-900">{{ intdiv((int) config('admin.idle_minutes'), 60) }} jam</dd>
        </div>
    </dl>

    <div class="space-y-3">
        <a href="{{ route('admin.account.password') }}" class="flex min-h-12 w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-900 hover:bg-gray-50">Ganti password</a>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-black px-5 text-sm font-semibold text-white hover:bg-gray-800">Keluar dari akun ini</button>
        </form>
    </div>

    <p class="text-sm leading-relaxed text-gray-500">HP toko dipakai bersama. Selalu tekan <strong class="font-semibold text-gray-700">Keluar</strong> setelah selesai supaya karyawan berikutnya masuk dengan akunnya sendiri.</p>
</div>
@endsection
