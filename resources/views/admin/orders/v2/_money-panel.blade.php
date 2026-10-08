{{--
    Super Admin money panel (ORD-02d, ADR-040): refund decision, refund payout, reconciliation. Also included on ORD-01
    pages, because a legacy cancel after payment needs reconciliation. Nothing here is ever assumed.
--}}
@use('App\Models\OnlineOrder')
@use('App\Support\OnlineOrderLabels')
@use('App\Support\OnlineOrderMoney')
@use('App\Support\Rupiah')
@php
    $money = OnlineOrderMoney::totals($order);
    $rp = fn (int $cents) => Rupiah::format(Rupiah::decimal($cents));
    $dueCents = $order->refund_due_amount !== null ? Rupiah::minorUnits($order->refund_due_amount) : null;
    $remainingRefund = $dueCents === null ? 0 : max(0, $dueCents - $money['refunded']);
    $input = 'mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-base focus:border-black focus:outline-none focus:ring-2 focus:ring-black/10';
    $button = 'inline-flex min-h-11 items-center justify-center rounded-lg bg-black px-4 text-sm font-semibold text-white hover:bg-gray-800 disabled:bg-gray-400';
@endphp
<section id="keuangan" class="scroll-mt-20 rounded-xl border border-gray-900/10 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="keuangan-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="keuangan-title" class="text-lg font-semibold text-gray-900">Keuangan · Super Admin</h2>
        @if ($order->refund_status)
            <span @class(['rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-red-100 text-red-800' => in_array($order->refund_status, ['needs_reconciliation', 'pending', 'partial'], true),
                'bg-gray-100 text-gray-700' => ! in_array($order->refund_status, ['needs_reconciliation', 'pending', 'partial'], true)])>{{ OnlineOrderLabels::REFUND[$order->refund_status] }}</span>
        @endif
    </div>
    @include('admin.orders.v2._notice', ['section' => 'keuangan'])

    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
        <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Diterima</dt><dd class="font-semibold tabular-nums">{{ $rp($money['received']) }}</dd></div>
        <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Harus kembali</dt><dd class="font-semibold tabular-nums">{{ $dueCents === null ? '—' : $rp($dueCents) }}</dd></div>
        <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Sudah kembali</dt><dd class="font-semibold tabular-nums">{{ $rp($money['refunded']) }}</dd></div>
    </dl>
    @if ($order->refund_reason)
        <p class="mt-2 text-sm text-gray-600">Alasan: {{ $order->refund_reason }}</p>
    @endif

    @if ($order->refund_status === 'needs_reconciliation')
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
            Pesanan ini dibatalkan setelah Lunas di sistem lama, dan riwayat refund-nya tidak tercatat. Isi angka sebenarnya dari mutasi/catatan kasir. Bila belum tahu, biarkan dulu — tanda ini tetap ada.
        </div>
        <form method="POST" action="{{ route('admin.orders.money.reconcile', $order) }}" class="mt-3 grid gap-3 sm:grid-cols-3" data-confirm="Simpan rekonsiliasi ini? Angka akan tercatat di riwayat keuangan.">
            @csrf <input type="hidden" name="revision" value="{{ $order->revision }}"> <input type="hidden" name="_section" value="keuangan">
            <label class="text-sm font-medium text-gray-700">Diterima dari customer (Rp)
                <input name="received" inputmode="numeric" required value="{{ old('received', $money['received'] > 0 ? (int) ($money['received'] / 100) : '') }}" class="{{ $input }}">
            </label>
            <label class="text-sm font-medium text-gray-700">Harus dikembalikan (Rp)
                <input name="refund_due" inputmode="numeric" required value="{{ old('refund_due') }}" placeholder="0 bila tidak ada" class="{{ $input }}">
            </label>
            <label class="text-sm font-medium text-gray-700">Sudah dikembalikan (Rp)
                <input name="already_refunded" inputmode="numeric" required value="{{ old('already_refunded') }}" placeholder="0 bila belum" class="{{ $input }}">
            </label>
            <label class="text-sm font-medium text-gray-700 sm:col-span-3">Sumber/catatan pengecekan
                <input name="note" required maxlength="300" value="{{ old('note') }}" placeholder="Misalnya: dicek di mutasi BCA 9 Okt" class="{{ $input }}">
            </label>
            <button class="{{ $button }} sm:col-span-3" data-busy-label="Menyimpan…">Simpan rekonsiliasi</button>
        </form>
    @elseif ($money['received'] > 0)
        <form method="POST" action="{{ route('admin.orders.money.refund-decision', $order) }}" class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-[12rem_1fr]" data-confirm="Simpan keputusan refund?">
            @csrf <input type="hidden" name="revision" value="{{ $order->revision }}"> <input type="hidden" name="_section" value="keuangan">
            <label class="text-sm font-medium text-gray-700">{{ $dueCents === null ? 'Nominal yang dikembalikan (Rp)' : 'Koreksi nominal refund (Rp)' }}
                <input name="refund_due" inputmode="numeric" required value="{{ old('refund_due', $dueCents !== null ? (int) ($dueCents / 100) : '') }}" placeholder="0 = tidak ada refund" class="{{ $input }}">
            </label>
            <label class="text-sm font-medium text-gray-700">Alasan
                <input name="reason" required maxlength="300" value="{{ old('reason') }}" placeholder="Misalnya: customer batal, dana dikembalikan penuh" class="{{ $input }}">
            </label>
            <button class="{{ $button }} sm:col-span-2" data-busy-label="Menyimpan…">{{ $dueCents === null ? 'Simpan keputusan refund' : 'Simpan koreksi' }}</button>
        </form>
        @if ($remainingRefund > 0)
            <form method="POST" action="{{ route('admin.orders.money.refunds', $order) }}" class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-3" data-confirm="Catat pengembalian dana ini?">
                @csrf <input type="hidden" name="revision" value="{{ $order->revision }}"> <input type="hidden" name="_section" value="keuangan">
                <p class="text-sm text-gray-700 sm:col-span-3">Sisa refund: <strong class="tabular-nums">{{ $rp($remainingRefund) }}</strong>. Boleh dibayar sebagian.</p>
                <label class="text-sm font-medium text-gray-700">Nominal dikembalikan (Rp)
                    <input name="amount" inputmode="numeric" required value="{{ old('amount', (int) ($remainingRefund / 100)) }}" class="{{ $input }}">
                </label>
                <label class="text-sm font-medium text-gray-700">Metode
                    <select name="refund_method" required class="{{ $input }}">@foreach (OnlineOrder::PAYMENT_METHODS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-medium text-gray-700">Referensi <span class="font-normal text-gray-500">(opsional)</span>
                    <input name="reference" maxlength="80" class="{{ $input }}">
                </label>
                <button class="{{ $button }} sm:col-span-3" data-busy-label="Mencatat…">Catat pengembalian dana</button>
            </form>
        @endif
    @else
        <p class="mt-3 text-sm text-gray-600">Belum ada pembayaran tercatat, jadi belum ada yang perlu dikembalikan.</p>
    @endif
</section>
