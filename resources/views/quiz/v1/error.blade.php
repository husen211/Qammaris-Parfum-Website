@extends('layouts.app')
@section('title', 'Tes belum berhasil — Qammaris')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-2xl px-5 py-16"><h1 class="font-mayluxa text-4xl">Tes belum berhasil diproses</h1><p class="mt-4">Silakan coba lagi. Jawaban sementara tetap tersedia di browser ini; kami belum mengonfirmasi penyimpanan hasil.</p><a class="pref-button mt-6 inline-flex" href="{{ $retry }}">Coba lagi</a></section>
@endsection
