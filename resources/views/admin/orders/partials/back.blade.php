{{-- ADM-02: back to the list exactly as it was left (tab, search, page), remembered by the list itself. --}}
@php
    $listUrl = session('admin.orders.list_url');
    $listUrl = is_string($listUrl) && str_starts_with($listUrl, route('admin.orders.index')) ? $listUrl : route('admin.orders.index');
@endphp
<a href="{{ $listUrl }}" class="-ml-2 inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-sm text-gray-600 hover:bg-gray-100 hover:text-black" data-orders-back>← Pesanan Online</a>
