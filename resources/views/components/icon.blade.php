@props(['name', 'filled' => false])
{{-- Lucide SVGs copied from 21st.dev/community/icons/lucide; licences in docs/licenses/lucide.txt. --}}
<svg {{ $attributes->class(['q-icon']) }} width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    @switch($name)
        @case('globe')
            <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></g>
            @break
        @case('phone')
            <path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            @break
        @case('map-pin')
            <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></g>
            @break
        @case('star')
            <path fill="{{ $filled ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.12 2.12 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.12 2.12 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.12 2.12 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.12 2.12 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.12 2.12 0 0 0 1.597-1.16z"></path>
            @break
        @case('plus')
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7v14"></path>
            @break
        @case('minus')
            <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></g>
            @break
        @case('arrow-up-right')
            <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10"></path><path d="M7 17 17 7"></path></g>
            @break
        @case('arrow-right')
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7l7 7l-7 7"></path>
            @break
        @case('arrow-left')
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 19l-7-7l7-7m7 7H5"></path>
            @break
        @case('arrow-down')
            <g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></g>
            @break
        @case('arrow-up')
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12l7-7l7 7m-7 7V5"></path>
            @break
        @case('play')
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"></path>
            @break
        @case('x')
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 6L6 18M6 6l12 12"></path>
            @break
    @endswitch
</svg>
