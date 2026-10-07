{{-- Inert templates contain no product/customer data and never replace server-rendered content. --}}
<div data-navigation-status role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>
<div data-navigation-loader hidden>
    <div class="navigation-skeleton-shell">
        <p data-navigation-label class="navigation-skeleton-label"></p>
        <div data-navigation-recovery hidden class="navigation-skeleton-recovery">
            <button type="button" data-navigation-cancel>Kembali</button>
        </div>
        <div data-navigation-shape aria-hidden="true"></div>
    </div>
</div>
@foreach(['home', 'catalog', 'product', 'cart', 'checkout', 'login', 'content', 'article', 'blog', 'quiz', 'quiz-result', 'admin-list', 'admin-form', 'admin-dashboard'] as $skeletonKind)
    <template data-navigation-skeleton="{{ $skeletonKind }}">
        @if($skeletonKind === 'catalog')
            <div class="sk-line sk-eyebrow"></div><div class="sk-line sk-title"></div>
            <div class="sk-toolbar"><div class="sk-line"></div><div class="sk-line"></div></div>
            <div class="sk-catalog">
                <div class="sk-sidebar">@for($i = 0; $i < 6; $i++)<div class="sk-field"></div>@endfor</div>
                <div class="sk-products">@for($i = 0; $i < 8; $i++)<div class="sk-card"><div class="sk-image"></div><div class="sk-line sk-eyebrow"></div><div class="sk-line"></div><div class="sk-line sk-short"></div><div class="sk-line sk-price"></div></div>@endfor</div>
            </div>
        @elseif($skeletonKind === 'product')
            <div class="sk-line sk-short"></div>
            <div class="sk-columns sk-product"><div><div class="sk-image"></div><div class="sk-thumbnails">@for($i = 0; $i < 3; $i++)<div class="sk-image"></div>@endfor</div></div><div><div class="sk-line sk-eyebrow"></div><div class="sk-line sk-title"></div><div class="sk-line sk-title sk-short"></div><div class="sk-toolbar"><div class="sk-line sk-short"></div><div class="sk-line sk-price"></div></div><div class="sk-line sk-short"></div><div class="sk-field sk-short"></div><div class="sk-action"></div><div class="sk-action"></div>@for($i = 0; $i < 3; $i++)<div class="sk-line"></div>@endfor</div></div>
        @elseif(in_array($skeletonKind, ['cart', 'checkout']))
            <div class="sk-line sk-title"></div>
            <div class="sk-columns sk-order"><div>
                @if($skeletonKind === 'cart')
                    @for($i = 0; $i < 3; $i++)<div class="sk-row"><div class="sk-image"></div><div><div class="sk-line"></div><div class="sk-line sk-short"></div><div class="sk-field sk-short"></div></div></div>@endfor
                @else
                    @for($i = 0; $i < 5; $i++)<div class="sk-line sk-eyebrow"></div><div class="sk-field {{ $i === 2 ? 'sk-address' : '' }}"></div>@endfor
                @endif
            </div><div class="sk-summary"><div class="sk-line"></div>@for($i = 0; $i < 3; $i++)<div class="sk-line sk-short"></div>@endfor<div class="sk-action"></div></div></div>
        @elseif($skeletonKind === 'login')
            <div class="sk-login"><div class="sk-line sk-title"></div>@for($i = 0; $i < 2; $i++)<div class="sk-line sk-eyebrow"></div><div class="sk-field"></div>@endfor<div class="sk-action"></div></div>
        @elseif($skeletonKind === 'admin-list')
            <div class="sk-toolbar"><div class="sk-line sk-title"></div><div class="sk-field sk-short"></div></div>
            <div class="sk-toolbar">@for($i = 0; $i < 3; $i++)<div class="sk-field"></div>@endfor</div>
            <div class="sk-table">@for($i = 0; $i < 7; $i++)<div class="sk-row"><div class="sk-image"></div><div><div class="sk-line"></div><div class="sk-line sk-short"></div></div><div class="sk-line sk-price"></div></div>@endfor</div>
        @elseif($skeletonKind === 'admin-form')
            <div class="sk-line sk-title"></div><div class="sk-form">@for($i = 0; $i < 6; $i++)<div><div class="sk-line sk-eyebrow"></div><div class="sk-field"></div></div>@endfor<div class="sk-action"></div></div>
        @elseif($skeletonKind === 'admin-dashboard')
            <div class="sk-line sk-title"></div><div class="sk-dashboard">@for($i = 0; $i < 4; $i++)<div class="sk-summary"><div class="sk-line sk-short"></div><div class="sk-line sk-title"></div></div>@endfor</div><div class="sk-field sk-address"></div>
        @elseif($skeletonKind === 'home')
            <div class="sk-columns"><div><div class="sk-line sk-eyebrow"></div><div class="sk-line sk-title"></div><div class="sk-line sk-title sk-short"></div><div class="sk-line"></div><div class="sk-action sk-short"></div></div><div class="sk-image"></div></div>
        @elseif(in_array($skeletonKind, ['blog', 'quiz-result']))
            <div class="sk-line sk-title"></div><div class="sk-products">@for($i = 0; $i < 4; $i++)<div class="sk-card"><div class="sk-image"></div><div class="sk-line"></div><div class="sk-line sk-short"></div></div>@endfor</div>
        @elseif($skeletonKind === 'quiz')
            <div class="sk-article"><div class="sk-line sk-eyebrow"></div><div class="sk-line sk-title"></div><div class="sk-line"></div>@for($i = 0; $i < 4; $i++)<div class="sk-field"></div>@endfor<div class="sk-action sk-short"></div></div>
        @else
            <div class="sk-article"><div class="sk-line sk-eyebrow"></div><div class="sk-line sk-title"></div><div class="sk-image"></div>@for($i = 0; $i < 5; $i++)<div class="sk-line {{ $i === 4 ? 'sk-short' : '' }}"></div>@endfor</div>
        @endif
    </template>
@endforeach
