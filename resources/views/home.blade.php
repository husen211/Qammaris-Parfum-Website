@extends('layouts.app')

@section('title', 'Toko Parfum Timur Tengah & Lokal di Palu - Qammaris Perfumes')
@section('meta_description', 'Toko parfum Timur Tengah dan lokal terlengkap di Kota Palu. Coba tester langsung di Jl. Sis Aljufri, konsultasi aroma, atau pesan online dengan pengiriman ke seluruh Indonesia.')

@push('preload')
    <link rel="preload" href="{{ asset('images/hero/hero-mobile-poster.webp') }}" as="image" type="image/webp" media="(max-width: 767px)" fetchpriority="high">
@endpush

@section('content')
    @include('home.sections.hero')
    @include('home.sections.brand-marquee')
    @include('home.sections.best-seller')
    @include('home.sections.quiz-cta')
    @include('home.sections.shopping-guide')
    @include('home.sections.journal')
    @include('home.sections.about')
@endsection
