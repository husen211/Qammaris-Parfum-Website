@props(['bank'])
@php
    // ORD-13: a recognisable mark per bank (its colour and short name) next to the account; not the banks' logo files.
    $marks = ['bri' => ['BRI', '#00529C'], 'bca' => ['BCA', '#0060AF'], 'mandiri' => ['mandiri', '#003D79'], 'bni' => ['BNI', '#F15A22'], 'bsi' => ['BSI', '#00A39D']];
    $key = collect(array_keys($marks))->first(fn ($name) => preg_match('/\b'.$name.'\b/i', (string) $bank));
    [$label, $color] = $marks[$key] ?? [mb_strtoupper(mb_substr(trim((string) $bank), 0, 3)), '#4B5563'];
@endphp
<span {{ $attributes->class(['inline-flex h-10 w-16 shrink-0 items-center justify-center rounded-md px-1 font-bold italic tracking-wide text-white', strlen($label) > 4 ? 'text-xs' : 'text-base']) }} style="background-color: {{ $color }}" aria-hidden="true" data-bank-mark="{{ $key ?? 'other' }}">{{ $label }}</span>
