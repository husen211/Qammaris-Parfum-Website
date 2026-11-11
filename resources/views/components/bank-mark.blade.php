@props(['bank'])
@php
    // ORD-13: a recognisable mark per bank (its colour and short name) next to the account. ORD-15: BRI uses its real
    // logo (public domain, see docs/licenses/partner-logos.txt); other banks keep the colour mark until a logo is added.
    $marks = ['bri' => ['BRI', '#00529C'], 'bca' => ['BCA', '#0060AF'], 'mandiri' => ['mandiri', '#003D79'], 'bni' => ['BNI', '#F15A22'], 'bsi' => ['BSI', '#00A39D']];
    $key = collect(array_keys($marks))->first(fn ($name) => preg_match('/\b'.$name.'\b/i', (string) $bank));
    [$label, $color] = $marks[$key] ?? [mb_strtoupper(mb_substr(trim((string) $bank), 0, 3)), '#4B5563'];
@endphp
@if (\App\Support\PartnerLogos::file($key))
<span {{ $attributes->class('inline-flex h-10 w-16 shrink-0 items-center justify-center rounded-md border border-gray-200 bg-white p-1.5') }} aria-hidden="true" data-bank-mark="{{ $key }}"><x-partner-logo :name="$key" class="max-h-full max-w-full" /></span>
@else
<span {{ $attributes->class(['inline-flex h-10 w-16 shrink-0 items-center justify-center rounded-md px-1 font-bold italic tracking-wide text-white', strlen($label) > 4 ? 'text-xs' : 'text-base']) }} style="background-color: {{ $color }}" aria-hidden="true" data-bank-mark="{{ $key ?? 'other' }}">{{ $label }}</span>
@endif
