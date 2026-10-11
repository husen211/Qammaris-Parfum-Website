@extends('layouts.admin')
@push('styles') @vite('resources/css/blog-editor.css') @endpush
@section('content')
<div class="journal-editor max-w-4xl">
<div class="journal-heading"><div><h1>Kategori dan tag Journal</h1><p>Nonaktifkan pilihan tanpa menghapus artikel. Nama dan URL tetap disimpan.</p></div><a href="{{ route('admin.blog-posts.index') }}">Kembali ke artikel</a></div>
@if($errors->any())<p role="alert" class="journal-errors">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('admin.blog-taxonomy.store') }}" class="journal-section journal-section-body">@csrf
<label for="kind">Jenis</label><select id="kind" name="kind"><option value="category">Kategori</option><option value="tag">Tag</option></select>
<label for="name">Nama baru</label><input id="name" name="name" maxlength="100" required value="{{ old('name') }}"><button class="journal-primary" type="submit">Tambahkan</button></form>
@foreach(['category'=>['Kategori',$categories], 'tag'=>['Tag',$tags]] as $kind=>[$label,$items])
<section class="journal-section journal-section-body"><h2>{{ $label }}</h2>@forelse($items as $item)<form method="POST" action="{{ route('admin.blog-taxonomy.status', [$kind, $item->id]) }}" class="journal-taxonomy-row">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}"><span>{{ $item->name }} · {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span><button type="submit" aria-label="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $item->name }}">{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>@empty<p>Belum ada {{ strtolower($label) }}.</p>@endforelse</section>
@endforeach
</div>
@endsection
