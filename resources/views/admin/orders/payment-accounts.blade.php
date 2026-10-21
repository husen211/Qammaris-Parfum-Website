@extends('layouts.admin')

{{-- ORD-04: official accounts for payment instructions. Verified = re-typed number matched; never edited, only deactivated. --}}
@php
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $time = fn ($value) => $value?->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i');
@endphp

@section('content')
<div class="mx-auto max-w-3xl space-y-4">
    <div>
        @include('admin.orders.partials.back')
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Rekening pembayaran</h1>
        <p class="mt-1 text-sm text-gray-600">Rekening resmi toko untuk instruksi pembayaran dan halaman status customer. Hanya rekening terverifikasi yang ditampilkan.</p>
    </div>

    @if (session('success'))<div role="status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{{ session('success') }}</div>@endif
    @if (session('error'))<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="accounts-title">
        <h2 id="accounts-title" class="text-base font-semibold text-gray-900">Daftar rekening</h2>
        @forelse ($accounts as $account)
            <div class="mt-3 rounded-lg border p-3 text-sm {{ $account->isUsable() ? 'border-green-200' : 'border-gray-200' }}" data-account="{{ $account->id }}">
                <p class="font-semibold text-gray-900">{{ $account->bank_name }} · <span class="font-mono">{{ $account->account_number }}</span></p>
                <p class="text-gray-700">a.n. {{ $account->account_holder }}</p>
                <p class="mt-1 text-xs text-gray-500">
                    Ditambahkan {{ $names[$account->created_by] ?? '-' }} · {{ $time($account->created_at) }}
                    @if ($account->verified_at) · diverifikasi {{ $names[$account->verified_by] ?? '-' }} · {{ $time($account->verified_at) }} @endif
                    @if ($account->deactivated_at) · dinonaktifkan {{ $time($account->deactivated_at) }} @endif
                </p>
                <p class="mt-1 text-xs font-semibold {{ $account->isUsable() ? 'text-green-800' : 'text-amber-900' }}">
                    {{ $account->deactivated_at ? 'Nonaktif' : ($account->verified_at ? 'Terverifikasi · dipakai' : 'Belum diverifikasi · belum dipakai') }}
                </p>
                @if (! $account->verified_at && ! $account->deactivated_at)
                    <form method="POST" action="{{ route('admin.orders.payment-accounts.verify', $account) }}" class="mt-2 space-y-2">
                        @csrf
                        <label class="block text-sm font-medium text-gray-700">Ketik ulang nomor rekening
                            <input name="retyped_number" inputmode="numeric" autocomplete="off" required class="{{ $input }} font-mono">
                        </label>
                        @error('retyped_number')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                        <label class="flex min-h-11 items-center gap-3 text-sm text-gray-700"><input type="checkbox" name="confirmed" value="1" required class="h-5 w-5"> Saya sudah memeriksa rekening ini atas nama toko.</label>
                        @error('confirmed')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                        <button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-black px-4 text-sm font-semibold text-white hover:bg-gray-800">Verifikasi</button>
                    </form>
                @endif
                @if (! $account->deactivated_at)
                    <form method="POST" action="{{ route('admin.orders.payment-accounts.deactivate', $account) }}" class="mt-2" onsubmit="return confirm('Nonaktifkan rekening ini? Instruksi pembayaran tidak lagi memakainya.')">
                        @csrf
                        <button class="inline-flex min-h-11 items-center text-sm font-semibold text-red-700">Nonaktifkan</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="mt-2 text-sm text-gray-600">Belum ada rekening. Instruksi pembayaran hanya berisi total, dan rekening dikirim admin di chat.</p>
        @endforelse
    </section>

    @if (\App\Models\StoreInfo::usesTestWhatsapp())
        <section class="rounded-xl border border-amber-300 bg-amber-50 p-4 sm:p-5" aria-labelledby="uat-wa-title" data-uat-whatsapp>
            <h2 id="uat-wa-title" class="text-base font-semibold text-gray-900">Uji WhatsApp nomor resmi (khusus UAT)</h2>
            @php($officialOn = \App\Models\StoreInfo::usesOfficialInTest())
            <p class="mt-1 text-sm text-amber-900">
                {{ $officialOn ? 'Aktif di browser ini: checkout membuka WhatsApp resmi toko, pesan diawali [UAT].' : 'Nonaktif: pengunjung UAT tidak pernah diarahkan ke nomor toko.' }}
                Hanya berlaku di browser ini selama {{ config('store.uat_official_minutes') }} menit; pengunjung lain tetap terlindungi.
            </p>
            <form method="POST" action="{{ route('admin.orders.uat-whatsapp') }}" class="mt-3">
                @csrf <input type="hidden" name="on" value="{{ $officialOn ? 0 : 1 }}">
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg border border-amber-400 bg-white px-4 text-sm font-semibold text-amber-900 hover:bg-amber-100">{{ $officialOn ? 'Matikan uji nomor resmi' : 'Uji nomor resmi di browser ini' }}</button>
            </form>
        </section>
    @endif

    <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5" aria-labelledby="add-title">
        <h2 id="add-title" class="text-base font-semibold text-gray-900">Tambah rekening</h2>
        <form method="POST" action="{{ route('admin.orders.payment-accounts.store') }}" class="mt-3 space-y-3">
            @csrf
            <label class="block text-sm font-medium text-gray-700">Bank<input name="bank_name" value="{{ old('bank_name') }}" required maxlength="40" placeholder="BCA" class="{{ $input }}"></label>
            @error('bank_name')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            <label class="block text-sm font-medium text-gray-700">Nomor rekening<input name="account_number" value="{{ old('account_number') }}" required inputmode="numeric" maxlength="30" class="{{ $input }} font-mono"></label>
            @error('account_number')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            <label class="block text-sm font-medium text-gray-700">Atas nama<input name="account_holder" value="{{ old('account_holder') }}" required maxlength="80" class="{{ $input }}"></label>
            @error('account_holder')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            <button class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-black px-4 text-sm font-semibold text-white hover:bg-gray-800 sm:w-auto">Tambah rekening</button>
            <p class="text-xs text-gray-500">Rekening tidak bisa diubah. Bila salah, nonaktifkan lalu tambah yang benar.</p>
        </form>
    </section>
</div>
@endsection
