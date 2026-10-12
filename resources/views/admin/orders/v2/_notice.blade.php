{{-- Result of the last action in this section, with its field errors (ORD-02d). --}}
@php
    $notice = session('order_notice');
    $mine = $notice && ($notice['section'] ?? null) === $section;
    $fieldErrors = old('_section') === $section || $mine ? $errors->all() : [];
@endphp
@if ($mine || $fieldErrors)
    @php($type = $mine ? $notice['type'] : 'error')
    <div role="{{ $type === 'success' ? 'status' : 'alert' }}" data-order-notice="{{ $type }}" @class([
        'mt-3 rounded-lg border px-3 py-2.5 text-sm',
        'border-green-200 bg-green-50 text-green-800' => $type === 'success',
        'border-red-200 bg-red-50 text-red-800' => $type === 'error',
        'border-amber-300 bg-amber-50 text-amber-900' => $type === 'conflict',
    ])>
        @if ($type === 'conflict')<p class="font-semibold">Pesanan berubah</p>@endif
        @if ($mine)<p>{{ $notice['message'] }}</p>@endif
        @if ($fieldErrors && $type !== 'success')
            <ul class="mt-1 list-disc pl-5">@foreach ($fieldErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
        @endif
    </div>
@endif
