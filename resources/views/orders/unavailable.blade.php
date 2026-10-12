@extends(empty($staff) ? 'layouts.app' : 'orders.task-layout')
@section('title', 'Link pesanan tidak berlaku - Qammaris Perfumes')
@section('robots', 'noindex,nofollow')
@push('meta')
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
<section class="{{ empty($staff) ? 'min-h-[70vh] px-4 pb-16 pt-28 md:pt-32' : 'px-4 py-10' }} bg-white" aria-labelledby="unavailable-title">
    <div class="mx-auto max-w-xl">
        <h1 id="unavailable-title" class="font-mayluxa text-3xl text-brand-black">Link tidak berlaku</h1>
        <p class="mt-4 text-base leading-7 text-gray-700">
            {{ empty($staff)
                ? 'Link pesanan ini sudah kedaluwarsa atau diganti dengan link baru. Silakan minta link terbaru kepada admin Qammaris.'
                : 'Link tugas ini sudah diganti atau pesanan sudah lama selesai. Minta link terbaru di grup.' }}
        </p>
        @if ($whatsappUrl)
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="mt-6 flex min-h-14 w-full items-center justify-center bg-brand-black px-4 text-sm font-semibold uppercase tracking-widest text-white active:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-black sm:w-auto sm:px-8">Chat admin di WhatsApp</a>
        @endif
    </div>
</section>
@endsection
