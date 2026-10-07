@php
    $tone = match ($order->stage) {
        'awaiting_customer' => 'bg-gray-100 text-gray-700',
        'details_received' => 'bg-amber-50 text-amber-800',
        'paid' => 'bg-blue-50 text-blue-800',
        'shipped' => 'bg-indigo-50 text-indigo-800',
        'completed' => 'bg-green-50 text-green-800',
        default => 'bg-red-50 text-red-800',
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">{{ $order->stage === 'details_received' ? 'Perlu dibayar' : $order->stageLabel() }}</span>
