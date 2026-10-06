@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <a href="{{ route('admin.brands.index') }}" class="text-sm font-medium text-gray-500 hover:text-black">← Kembali ke Brand</a>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">Tambah Brand</h1>
        <p class="mt-1 text-sm text-gray-500">Slug dibuat otomatis dan dijaga tetap stabil setelah brand dibuat.</p>
    </div>

    <form method="POST" action="{{ route('admin.brands.store') }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @include('admin.brands._form', ['submitLabel' => 'Simpan Brand'])
    </form>
</div>
@endsection
