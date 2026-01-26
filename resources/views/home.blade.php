@extends('layouts.app')

@section('title', 'Qammaris Perfumes - Premium Middle Eastern Fragrances')

@section('content')
    @include('home.sections.hero')
    @include('home.sections.brand-marquee')
    @include('home.sections.best-seller')
    @include('home.sections.quiz-cta')
    @include('home.sections.shopping-guide')
    @include('home.sections.journal')
    @include('home.sections.about')
@endsection
