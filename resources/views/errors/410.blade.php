@extends('layouts.app')
@section('title', 'Hasil tes kedaluwarsa — Qammaris')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-2xl px-5 pt-32 pb-16"><h1 class="font-mayluxa text-4xl">Hasil tes sudah kedaluwarsa</h1><p class="mt-4">Akses hasil berlaku tujuh hari di browser yang sama. Kamu bisa mulai tes baru untuk mendapatkan pilihan dari katalog terbaru.</p><a class="pref-button mt-6 inline-flex" href="{{ route('quiz.index') }}">Mulai tes baru</a></section>
@endsection
