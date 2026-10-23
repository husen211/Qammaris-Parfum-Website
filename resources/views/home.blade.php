@extends('layouts.app')

@section('title', 'Qammaris Perfumes - Premium Middle Eastern Fragrances')

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
