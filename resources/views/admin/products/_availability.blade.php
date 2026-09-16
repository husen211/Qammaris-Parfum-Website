@php
    $effectiveAvailability = $product->effective_availability;
    $availabilityLabel = match ($effectiveAvailability) {
        'available' => 'Tersedia',
        'sold_out' => 'Sold out',
        default => 'Belum dikonfirmasi',
    };
    $availabilityClasses = match ($effectiveAvailability) {
        'available' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'sold_out' => 'bg-red-50 text-red-800 ring-red-200',
        default => 'bg-gray-100 text-gray-700 ring-gray-200',
    };
@endphp

<div class="flex flex-col items-start gap-1.5">
    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $availabilityClasses }}">
        {{ $availabilityLabel }}
    </span>
    <span class="text-xs text-gray-500">
        @if($product->availability_checked_at)
            Diperiksa {{ $product->availability_checked_at->format('d M, H:i') }}
            @if($product->availability_source)
                · {{ $product->availability_source }}
            @endif
        @else
            Belum pernah diperiksa
        @endif
    </span>
</div>
