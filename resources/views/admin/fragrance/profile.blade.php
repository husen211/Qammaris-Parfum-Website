@extends('layouts.admin')
@section('content')
<div class="max-w-4xl space-y-5">
 <a class="underline" href="{{ route('admin.fragrance.index') }}">Kembali ke evaluasi</a><h1 class="text-2xl">Review profil: {{ $product->name }}</h1>
 @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 <p>Revision {{ $profile->revision }}. Koreksi hanya profil rekomendasi. Jangan menyatakan pengamatan aroma yang belum dilakukan.</p>
 <details open><summary>Data efektif dan bukti sumber</summary><pre class="whitespace-pre-wrap break-words bg-white p-4 text-sm">{{ json_encode($effective, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
 @if(!$effective)<p>Sumber atau parser berubah. Bangun ulang melalui preview sebelum koreksi.</p>@else
 <form method="post" action="{{ route('admin.fragrance.review', $product->id) }}" class="space-y-3">@csrf<input type="hidden" name="revision" value="{{ $profile->revision }}"><input type="hidden" name="source" value="{{ $profile->source_fingerprint }}"><label class="block">Koreksi atribut (JSON)<textarea name="changes" required maxlength="4000" class="block border p-3 w-full" rows="4" placeholder='{"sweetness":"light"}'></textarea></label><p class="text-sm">Atribut: aroma_target, sweetness, projection, longevity, context, identity. Lihat runbook untuk nilai dan batas. Identitas ukuran hanya setelah verifikasi parfum yang sama.</p><label class="block">Bukti dan dasar koreksi<textarea required name="evidence" minlength="8" maxlength="2000" class="block border p-3 w-full" rows="4"></textarea></label><button class="pref-button">Simpan koreksi profil</button></form>
 @endif
</div>
@endsection
