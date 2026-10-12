{{-- Blade port of the 21st.dev Timeline (preetsuthar17): status icon, connector, title with inline time, description. --}}
@props(['items', 'label' => 'Status pesanan'])

<ol class="relative flex flex-col gap-4" aria-label="{{ $label }}">
    @foreach ($items as $item)
        @php
            $status = $item['status'];
            $icon = match ($status) {
                'completed' => 'border-brand-black bg-brand-black text-white',
                'active' => 'border-brand-gold bg-white text-brand-gold',
                'error' => 'border-red-700 bg-red-700 text-white',
                default => 'border-gray-300 bg-white text-gray-400',
            };
            $connector = $status === 'completed' ? 'bg-brand-black' : 'bg-gray-300';
            $statusText = ['completed' => 'Selesai', 'active' => 'Sedang berjalan', 'error' => 'Dibatalkan'][$status] ?? 'Belum';
        @endphp
        <li class="relative flex gap-3 pb-2" @if ($status === 'active') aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute left-3 top-8 h-full w-px {{ $connector }}" aria-hidden="true"></span>
            @endunless
            <span class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 {{ $icon }} {{ $status === 'active' ? 'motion-safe:animate-pulse' : '' }}" aria-hidden="true">
                @if ($status === 'completed')
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                @elseif ($status === 'error')
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                @elseif ($status === 'active')
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @else
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                @endif
            </span>
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-0.5">
                    <p class="font-medium leading-6 {{ $status === 'pending' ? 'text-gray-500' : 'text-brand-black' }}">
                        {{ $item['title'] }}<span class="sr-only"> — {{ $statusText }}</span>
                    </p>
                    @if ($item['time'])
                        <time datetime="{{ $item['time']->toIso8601String() }}" class="shrink-0 text-xs leading-6 text-gray-500 tabular-nums">
                            {{ $item['time']->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M, H.i') }}
                        </time>
                    @endif
                </div>
                @if ($item['description'])
                    <p class="text-sm leading-relaxed text-gray-600">{{ $item['description'] }}</p>
                @endif
                @if (! empty($item['actor']))
                    <p class="text-xs text-gray-500">oleh {{ $item['actor'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
