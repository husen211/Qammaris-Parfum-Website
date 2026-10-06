@php
    $publicationStatus = $product->publication_status ?? 'draft';
    $publicationLabel = match ($publicationStatus) {
        'published' => 'Tayang',
        'archived' => 'Diarsipkan',
        default => 'Draft',
    };
    $publicationClasses = match ($publicationStatus) {
        'published' => 'text-emerald-700',
        'archived' => 'text-gray-500',
        default => 'text-amber-700',
    };
    $publicationDotClasses = match ($publicationStatus) {
        'published' => 'bg-emerald-500',
        'archived' => 'bg-gray-400',
        default => 'bg-amber-500',
    };
@endphp

<span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $publicationClasses }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $publicationDotClasses }}" aria-hidden="true"></span>
    {{ $publicationLabel }}
</span>
