{{-- Customer link + invite and the staff group message (ORD-02d). Uses variables from the V2 show view. --}}
<section id="link" class="{{ $card }}" aria-labelledby="link-title">
    <h2 id="link-title" class="text-lg font-semibold text-gray-900">Link customer & WhatsApp</h2>
    @include('admin.orders.v2._notice', ['section' => 'link'])
    @if ($order->lifecycle === 'awaiting_customer')
        @if ($order->customerLinkUsable())
            <p class="mt-1 text-sm text-gray-600">Kirim link ini agar customer mengisi data. Berlaku sampai {{ $time($order->customer_link_expires_at) }}. Bila data sudah lengkap dari chat, isi di bagian Detail — customer tidak perlu membuka form.</p>
        @else
            <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Link sudah kedaluwarsa. Buat link baru lalu kirim ulang.</p>
        @endif
    @else
        <p class="mt-1 text-sm text-gray-600">Customer memakai link yang sama untuk melihat status pesanan.</p>
    @endif
    <div class="mt-3 flex gap-2">
        <label for="customer-link" class="sr-only">Link customer</label>
        <input id="customer-link" readonly value="{{ $customerUrl }}" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 font-mono text-xs">
        <button type="button" class="{{ $secondary }} shrink-0" data-copy="#customer-link">Salin link</button>
    </div>
    <details class="mt-3" @if ($order->lifecycle === 'awaiting_customer') open @endif>
        <summary class="{{ $quiet }} cursor-pointer">Pesan untuk customer</summary>
        <label for="invite-message" class="sr-only">Pesan untuk customer</label>
        <textarea id="invite-message" readonly rows="6" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-6">{{ $inviteMessage }}</textarea>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="{{ $secondary }}" data-copy="#invite-message">Salin pesan</button>
            <a href="{{ $inviteUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $secondary }}">Kirim via WhatsApp</a>
        </div>
    </details>
    <details class="mt-2">
        <summary class="{{ $quiet }} cursor-pointer">Pesan untuk grup staf</summary>
        <label for="group-message" class="sr-only">Pesan untuk grup staf</label>
        <textarea id="group-message" readonly rows="10" class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 p-3 font-mono text-sm leading-6">{{ $groupMessage }}</textarea>
        <div class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="{{ $secondary }}" data-copy="#group-message">Salin untuk grup</button>
            <a href="{{ $groupShareUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $secondary }}">Buka WhatsApp</a>
        </div>
        <p class="mt-1 text-xs text-gray-500">Staf membuka pesanan di aplikasi Qammaris Admin dengan akun masing-masing.</p>
    </details>
    <form method="POST" action="{{ route('admin.orders.v2.customer-link', $order) }}" class="mt-2" data-confirm="Buat link baru? Link lama langsung tidak berlaku.">
        @csrf <input type="hidden" name="_section" value="link">
        <button class="{{ $quiet }}" data-busy-label="Membuat…">Buat link customer baru</button>
    </form>
</section>
