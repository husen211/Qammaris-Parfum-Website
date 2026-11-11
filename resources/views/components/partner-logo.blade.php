@props(['name', 'label' => null])
@php
    // ORD-15: courier and bank logos (public domain on Wikimedia Commons, see docs/licenses/partner-logos.txt), shown
    // small beside the name. Decorative by default: the name is already in the text next to it.
    $file = \App\Support\PartnerLogos::file($name);
@endphp
@if ($file)<img src="{{ asset('images/partners/'.$file) }}" alt="{{ $label ?? '' }}" @if (! $label) aria-hidden="true" @endif {{ $attributes->class('w-auto shrink-0 object-contain') }} decoding="async" data-partner-logo="{{ $name }}">@endif
