{{-- ORD-03: "Link Pesanan" — copy the link, copy the message, or share through the device share sheet. WhatsApp is not required. --}}
<details id="link" class="{{ $card }}" @if ($openSection ?? true) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection ?? false) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900" id="link-title">Link Pesanan</span><span class="block text-sm text-gray-600">{{ $order->lifecycle === 'awaiting_customer' ? 'Menunggu customer mengisi data' : 'Untuk melihat status pesanan' }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'link'])
    @if ($order->lifecycle === 'awaiting_customer' && ! $order->customerLinkUsable())
        <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Link sudah kedaluwarsa. Buat link baru.</p>
    @else
        <p class="mt-1 text-sm text-gray-600">{{ $order->lifecycle === 'awaiting_customer' ? 'Customer mengisi data lewat link ini (berlaku sampai '.$time($order->customer_link_expires_at).').' : 'Customer melihat status pesanan lewat link ini.' }}</p>
        <p class="mt-2 break-all rounded-lg bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700" data-link-text>{{ $customerUrl }}</p>
        <textarea id="invite-message" hidden aria-hidden="true">{{ $inviteMessage }}</textarea>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <button type="button" class="{{ $secondary }}" data-copy-text="{{ $customerUrl }}">Salin Link</button>
            <button type="button" class="{{ $secondary }}" data-copy="#invite-message">Salin Pesan</button>
            <button type="button" class="{{ $primary }} col-span-2 sm:col-span-1" data-share="#invite-message" data-share-title="Pesanan {{ $order->code }}" hidden>Bagikan</button>
        </div>
    @endif
    <details class="mt-3">
        <summary class="{{ $quiet }} cursor-pointer">Pesan untuk grup staf</summary>
        <textarea id="group-message" hidden aria-hidden="true">{{ $groupMessage }}</textarea>
        <p class="mt-1 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm leading-6 text-gray-800">{{ $groupMessage }}</p>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="{{ $secondary }}" data-copy="#group-message">Salin Pesan</button>
            <button type="button" class="{{ $secondary }}" data-share="#group-message" data-share-title="Pesanan {{ $order->code }}" hidden>Bagikan</button>
        </div>
    </details>
    <form method="POST" action="{{ route('admin.orders.v2.customer-link', $order) }}" class="mt-1" data-confirm="Buat link baru? Link lama langsung tidak berlaku.">
        @csrf <input type="hidden" name="_section" value="link">
        <button class="{{ $quiet }}" data-busy-label="Membuat…">Buat link baru</button>
    </form>
</details>
