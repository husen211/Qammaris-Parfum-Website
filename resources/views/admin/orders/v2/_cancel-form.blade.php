{{-- The cancel form, in Opsi lanjutan and (Owner 2026-11-17) in the Konfirmasi step, where staff find out stock is missing. --}}
<form method="POST" action="{{ route('admin.orders.v2.cancel', $order) }}" class="mt-2 space-y-3" data-confirm="Batalkan pesanan {{ $order->code }}?">
    @csrf {!! $hidden('batal') !!}
    <label class="block text-sm font-medium text-gray-700">Alasan<input name="reason" required minlength="5" maxlength="200" value="{{ old('reason') }}" placeholder="Contoh: stok habis" class="{{ $input }}"></label>
    @if ($received > 0)
        <label class="block text-sm font-medium text-gray-700">Dikembalikan ke customer (Rp)
            <input name="refund_due" data-rupiah-input required inputmode="numeric" value="{{ old('refund_due') }}" placeholder="0 sampai {{ (int) ($received / 100) }}" class="{{ $input }}">
        </label>
        <p class="text-xs text-gray-600">Sudah diterima {{ $rp($received) }}. Tulis 0 bila tidak dikembalikan.</p>
    @endif
    <button class="{{ $danger }}" data-busy-label="Membatalkan…">Ya, batalkan pesanan</button>
</form>
