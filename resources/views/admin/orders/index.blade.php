@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-400">Penjualan</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-gray-900">Pesanan Online</h1>
            <p class="mt-1 text-sm text-gray-500">Pesanan dari WhatsApp: link data customer, pembayaran, pengiriman, dan tugas staf.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @include('admin.orders.partials.refresh')
            @can('orders.payment-accounts')<a href="{{ route('admin.orders.payment-accounts.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100">Rekening pembayaran</a>@endcan
            <a href="{{ route('admin.orders.create') }}" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-black px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 sm:w-auto">+ Buat pesanan</a>
        </div>
    </div>

    {{-- ORD-09: on phones the sidebar is hidden, so the push switch sits on the order list. --}}
    <div class="md:hidden">@include('admin.partials.push-toggle')</div>

    @foreach (['reconcile' => 'pesanan perlu rekonsiliasi pembayaran', 'refund' => 'refund belum selesai', 'issues' => 'pesanan dengan kendala terbuka'] as $key => $label)
        @if ($attention[$key] > 0 && $filter !== $key)
            <a href="{{ route('admin.orders.index', ['status' => $key]) }}" class="flex min-h-11 items-center rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 hover:bg-red-100">{{ $attention[$key] }} {{ $label }} →</a>
        @endif
    @endforeach

    @if ($attention['website'] > 0 && $filter !== 'website')
        <a href="{{ route('admin.orders.index', ['status' => 'website']) }}" class="flex min-h-11 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 hover:bg-amber-100" data-website-pending>{{ $attention['website'] }} pesanan website menunggu konfirmasi →</a>
    @endif

    @if ($reimburseCount > 0 && $filter !== 'reimburse')
        <a href="{{ route('admin.orders.index', ['status' => 'reimburse']) }}" class="flex min-h-11 items-center rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 hover:bg-amber-100">
            {{ $reimburseCount }} talangan ongkir staf belum diganti →
        </a>
    @endif

    {{-- ADM-02: three tabs that apply on tap; the special filters come from the banners above. --}}
    @php($tabs = \App\Http\Controllers\Admin\AdminOnlineOrderController::TABS)
    <div class="space-y-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm sm:p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            @php($tabIndex = $search === '' ? array_search($filter, array_keys($tabs), true) : false)
            <nav aria-label="Status pesanan" class="relative grid grid-cols-3 rounded-lg bg-gray-100 p-1 lg:w-96 lg:shrink-0" data-order-tabs>
                {{-- One white pill slides to the current tab (no pill while searching or on a banner filter). --}}
                <span aria-hidden="true" data-tab-indicator style="--tab-index: {{ $tabIndex === false ? 0 : $tabIndex }}"
                    class="pointer-events-none absolute inset-y-1 left-1 w-[calc((100%-0.5rem)/3)] translate-x-[calc(var(--tab-index)*100%)] rounded-md bg-white shadow-sm transition-[translate,opacity] duration-300 ease-out motion-reduce:transition-none {{ $tabIndex === false ? 'opacity-0' : '' }}"></span>
                @foreach ($tabs as $value => $label)
                    @php($current = $search === '' && $filter === $value)
                    <a href="{{ route('admin.orders.index', $value === 'active' ? [] : ['status' => $value]) }}" @if ($current) aria-current="page" @endif data-tab="{{ $loop->index }}"
                        class="relative z-10 flex min-h-11 items-center justify-center rounded-md px-2 text-sm font-semibold transition-colors duration-300 {{ $current ? 'text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('admin.orders.index') }}" role="search" class="lg:flex-1">
                <label for="order-search" class="sr-only">Cari pesanan (kode, nama, atau nomor HP)</label>
                <input id="order-search" name="search" type="search" value="{{ $search }}" placeholder="Cari kode, nama, atau nomor HP" enterkeyhint="search" autocomplete="off" class="min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10 sm:text-sm">
            </form>
        </div>
        <div data-order-note-slot>
            @if ($search !== '')
                <p class="flex flex-wrap items-center gap-x-3 text-sm text-gray-600" data-order-filter-note>Hasil cari “{{ $search }}” di semua status. <a href="{{ route('admin.orders.index', array_key_exists($filter, $tabs) && $filter !== 'active' ? ['status' => $filter] : []) }}" class="inline-flex min-h-11 items-center font-semibold text-gray-900 underline underline-offset-4">Hapus pencarian</a></p>
            @elseif (! array_key_exists($filter, $tabs))
                <p class="flex flex-wrap items-center gap-x-3 text-sm text-gray-600" data-order-filter-note>Filter: <strong class="text-gray-900">{{ \App\Http\Controllers\Admin\AdminOnlineOrderController::FILTERS[$filter] }}</strong> <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center font-semibold text-gray-900 underline underline-offset-4">Hapus filter</a></p>
            @endif
        </div>
    </div>

    <div class="space-y-6 transition-opacity duration-200 aria-busy:opacity-50" data-order-results>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="hidden grid-cols-[7rem_minmax(0,1.2fr)_minmax(0,1.5fr)_10rem_9rem] gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-gray-500 lg:grid">
                <span>Kode</span><span>Customer</span><span>Produk</span><span>Status</span><span class="text-right">Dibuat</span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($orders as $order)
                    <a href="{{ route('admin.orders.show', $order) }}" class="grid gap-2 p-5 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none lg:grid-cols-[7rem_minmax(0,1.2fr)_minmax(0,1.5fr)_10rem_9rem] lg:items-center lg:gap-4">
                        <span class="font-mono text-sm font-semibold text-gray-900">{{ $order->code }}</span>
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-gray-900">{{ $order->customer_name ?? 'Menunggu customer' }}</span>
                            <span class="block text-xs text-gray-500">{{ \App\Models\OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? '—' }}</span>
                        </span>
                        <span class="min-w-0 text-sm text-gray-700"><span class="line-clamp-2">{{ $order->itemSummary() }}</span></span>
                        <span class="flex flex-wrap gap-1">
                            @if ($order->isV2())
                                @php($queue = \App\Support\OnlineOrderState::queue($order, $order->open_issues_count))
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->lifecycle === 'cancelled' ? 'bg-red-50 text-red-800' : ($order->lifecycle === 'completed' ? 'bg-green-50 text-green-800' : ($order->lifecycle === 'draft' ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-800')) }}">{{ $queue ? \App\Support\OnlineOrderLabels::QUEUE[$queue] : \App\Support\OnlineOrderLabels::LIFECYCLE[$order->lifecycle] }}</span>
                                @if ($order->payment_status !== 'paid' && $order->lifecycle === 'active')<span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ \App\Support\OnlineOrderLabels::PAYMENT[$order->payment_status] }}</span>@endif
                                @if ($order->keep_status === 'active')<span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-900">Keep</span>@endif
                            @else
                                @include('admin.orders._stage-badge', ['order' => $order])
                            @endif
                            @if ($order->refund_status === 'needs_reconciliation')<span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Rekonsiliasi</span>@endif
                            @if ($order->needsReimbursement())<span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Talangan</span>@endif
                        </span>
                        <span class="text-xs text-gray-500 lg:text-right">{{ $order->created_at->timezone('Asia/Makassar')->locale('id')->translatedFormat('j M Y, H.i') }}</span>
                    </a>
                @empty
                    <div class="px-5 py-14 text-center">
                        <p class="font-semibold text-gray-900">{{ $search !== '' ? 'Pesanan tidak ditemukan' : 'Belum ada pesanan' }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ match (true) {
                            $search !== '' => 'Coba kode, nama, atau nomor HP lain.',
                            $filter !== 'active' => 'Tidak ada pesanan di sini.',
                            default => 'Buat pesanan saat customer sudah fix order di WhatsApp.',
                        } }}</p>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($orders->hasPages())
            <div>{{ $orders->links('pagination::tailwind') }}</div>
        @endif
    </div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    // ADM-02: switching tabs slides the pill and swaps only the list; any failure falls back to a normal page load.
    const nav = document.querySelector('[data-order-tabs]');
    const results = document.querySelector('[data-order-results]');
    const noteSlot = document.querySelector('[data-order-note-slot]');
    if (!nav || !results || !window.fetch || !window.DOMParser) return;
    const indicator = nav.querySelector('[data-tab-indicator]');
    let pending;

    const markTab = (link) => {
        nav.querySelectorAll('a[data-tab]').forEach((tab) => {
            const current = tab === link;
            tab.toggleAttribute('aria-current', current);
            if (current) tab.setAttribute('aria-current', 'page');
            tab.classList.toggle('text-gray-900', current);
            tab.classList.toggle('text-gray-600', !current);
        });
        indicator.style.setProperty('--tab-index', link ? link.dataset.tab : 0);
        indicator.classList.toggle('opacity-0', !link);
    };

    const load = async (url, push) => {
        pending?.abort();
        pending = new AbortController();
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: pending.signal, credentials: 'same-origin' });
            if (!response.ok || response.redirected) throw new Error('reload');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextResults = page.querySelector('[data-order-results]');
            if (!nextResults) throw new Error('reload');
            results.replaceChildren(...nextResults.childNodes);
            noteSlot?.replaceChildren(...(page.querySelector('[data-order-note-slot]')?.childNodes ?? []));
            const search = document.getElementById('order-search');
            if (search) search.value = page.getElementById('order-search')?.value ?? '';
            if (push) history.pushState({ ordersList: true }, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            results.removeAttribute('aria-busy');
        }
    };

    nav.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-tab]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (link.getAttribute('aria-current') === 'page') return;
        markTab(link);
        load(link.href, true);
    });

    window.addEventListener('popstate', () => {
        const status = new URL(window.location.href).searchParams.get('status') ?? 'active';
        const link = [...nav.querySelectorAll('a[data-tab]')].find((tab) => (new URL(tab.href).searchParams.get('status') ?? 'active') === status);
        markTab(new URL(window.location.href).searchParams.get('search') ? null : link ?? null);
        load(window.location.href, false);
    });
})();
</script>
@endpush
