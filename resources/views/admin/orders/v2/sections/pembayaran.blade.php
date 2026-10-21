@use('App\Models\OnlineOrder')
@use('App\Support\OnlineOrderLabels')
{{-- ORD-03/04 payment: ongkir first, then the final total with copyable instructions, then one "Tandai Lunas". The ledger keeps the audit source. --}}
@php
    $paymentSummary = match (true) {
        $order->payment_status === 'paid' => 'Lunas'.($order->recorded_in_majoo ? ' · tercatat di Majoo' : ''),
        $received > 0 => 'Dibayar '.$rp($received).' · sisa '.$rp($remainingCents),
        default => 'Belum dibayar · '.$rp($totalCents),
    };
@endphp
<details id="pembayaran" class="{{ $card }}" @if ($openSection) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900">Pembayaran</span><span class="block text-sm text-gray-600">{{ $paymentSummary }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'pembayaran'])
    @if ($order->payments->isNotEmpty())
        <ul class="mt-3 divide-y divide-gray-100 text-sm" aria-label="Catatan pembayaran">
            @foreach ($order->payments as $entry)
                @php($reversed = $order->payments->contains('reverses_id', $entry->id))
                <li class="flex flex-wrap items-start justify-between gap-2 py-2 {{ $reversed ? 'text-gray-400 line-through' : '' }}">
                    <span class="min-w-0">
                        <span class="block font-medium {{ $entry->basis === 'reversal' ? 'text-red-700' : 'text-gray-900' }}">{{ $entry->basis === 'reversal' ? 'Pembatalan entri' : ($entry->type === 'refund' ? 'Refund' : 'Pembayaran') }}</span>
                        <span class="block text-xs text-gray-500">{{ OnlineOrder::PAYMENT_METHODS[$entry->method] ?? '' }} · {{ $entry->recorder?->name }} · {{ $time($entry->created_at) }}</span>
                    </span>
                    <span class="shrink-0 font-semibold tabular-nums">{{ $entry->basis === 'reversal' ? '−' : '' }}{{ format_rupiah($entry->amount) }}</span>
                    @can('orders.refund')
                        @if ($entry->basis !== 'reversal' && ! $reversed)
                            <details class="w-full">
                                <summary class="inline-flex min-h-9 cursor-pointer items-center text-xs font-semibold text-red-700">Batalkan entri ini…</summary>
                                <form method="POST" action="{{ route('admin.orders.money.reverse', [$order, $entry->id]) }}" class="mt-2 flex flex-wrap items-end gap-2" data-confirm="Batalkan entri ini? Entri asli tetap terlihat.">
                                    @csrf {!! $hidden('pembayaran') !!}
                                    <label class="min-w-0 flex-1 text-xs font-medium text-gray-700">Alasan<input name="reason" required maxlength="300" class="{{ $input }}"></label>
                                    <button class="{{ $secondary }}" data-busy-label="Membatalkan…">Batalkan entri</button>
                                </form>
                            </details>
                        @endif
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @include('admin.orders.v2._ongkir', ['section' => 'pembayaran'])

    @if ($order->lifecycle === 'draft')
        <p class="mt-3 text-sm text-gray-600">Pembayaran dicatat setelah pesanan website dikonfirmasi.</p>
    @elseif ($order->lifecycle !== 'cancelled' && $remainingCents > 0)
        @if (! $totalFinal)
            <p class="mt-3 text-sm text-gray-700">{{ $order->shipping_payer ? 'Isi nominal ongkir dulu supaya total pembayaran final.' : 'Isi ongkir dan metode pembayarannya dulu supaya total pembayaran final.' }}</p>
        @else
            {{-- ORD-04: total + official accounts for the customer; staff still mark paid only after the proof. --}}
            <div class="mt-3 rounded-lg bg-gray-50 p-3" data-payment-instructions>
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm text-gray-700">Total pembayaran</span>
                    <strong class="text-xl tabular-nums">{{ $rp($totalCents) }}</strong>
                </div>
                @if ($received > 0)<p class="text-sm text-gray-600">Sudah diterima {{ $rp($received) }} · sisa {{ $rp($remainingCents) }}</p>@endif
                @if ($order->payment_preference)
                    <p class="mt-1 text-sm text-gray-700">Customer memilih <strong>{{ config('orders.payment_preferences')[$order->payment_preference] ?? $order->payment_preference }}</strong> <span class="text-gray-500">(boleh berubah lewat chat)</span></p>
                @endif
                <textarea id="payment-message" readonly hidden>{{ $paymentText }}</textarea>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <button type="button" data-copy="#payment-message" class="{{ $secondary }} w-full">Salin Total & Instruksi Pembayaran</button>
                    @if ($paymentChatUrl)<a href="{{ $paymentChatUrl }}" target="_blank" rel="noopener noreferrer" class="{{ $secondary }} w-full gap-2" data-whatsapp-button><x-icon name="whatsapp" class="text-[1.2rem] text-[#128C7E]" /> Kirim ke WhatsApp customer</a>@endif
                </div>
                @if (! $hasPaymentAccount)
                    <p class="mt-2 text-xs text-amber-900">Belum ada rekening resmi terverifikasi; instruksi meminta customer menunggu nomor rekening dari admin.@can('orders.payment-accounts') <a href="{{ route('admin.orders.payment-accounts.index') }}" class="underline underline-offset-2">Atur rekening</a>@endcan</p>
                @endif
            </div>
            {{-- ADM-03 (Owner 2026-10-21): marking paid opens a short check; the payment must already be in the cashier (Majoo). --}}
            <form method="POST" action="{{ route('admin.orders.v2.payments', $order) }}" class="mt-3 space-y-3" data-paid-form>
                @csrf {!! $hidden('pembayaran') !!}
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium text-gray-700">Nominal diterima (Rp)
                        <input name="amount" data-paid-amount data-rupiah-input inputmode="numeric" required value="{{ old('_section') === 'pembayaran' ? old('amount') : (int) ($remainingCents / 100) }}" class="{{ $input }}">
                    </label>
                    <label class="text-sm font-medium text-gray-700">Metode
                        <select name="payment_method" data-paid-method required class="{{ $input }}">
                            @foreach (OnlineOrder::PAYMENT_METHODS as $value => $label)<option value="{{ $value }}" @selected(old('payment_method', $order->payment_preference ?? $order->payment_method ?? 'transfer') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
                @error('recorded_in_majoo') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                <button type="button" class="{{ $primary }}" data-paid-open>{{ $received === 0 ? 'Tandai Lunas' : 'Catat pembayaran' }}</button>
                <p class="text-xs text-gray-500">Packing dan pengiriman baru bisa setelah lunas.</p>
                <dialog aria-labelledby="paid-title" data-paid-dialog
                    class="mt-auto mb-0 w-full max-w-full rounded-t-2xl bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/40 transition duration-200 ease-out starting:open:translate-y-6 starting:open:opacity-0 motion-reduce:transition-none sm:m-auto sm:max-w-md sm:rounded-2xl">
                    <div class="space-y-4 p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                        <div>
                            <h3 id="paid-title" class="text-lg font-semibold">{{ $received === 0 ? 'Tandai lunas?' : 'Catat pembayaran?' }}</h3>
                            <p class="mt-1 text-sm text-gray-600" data-paid-summary></p>
                        </div>
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 text-sm font-medium has-[:checked]:border-black">
                            <input type="checkbox" required data-paid-check class="h-5 w-5"> Bukti pembayaran sudah dicek
                        </label>
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 text-sm font-medium has-[:checked]:border-black">
                            <input type="checkbox" name="recorded_in_majoo" value="1" required data-paid-check class="h-5 w-5"> Sudah dicatat di kasir (Majoo)
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" class="{{ $secondary }} min-h-12" data-paid-cancel>Batal</button>
                            <button class="{{ $primary }} sm:w-full" data-paid-submit disabled data-busy-label="Menyimpan…">{{ $received === 0 ? 'Ya, tandai lunas' : 'Ya, catat' }}</button>
                        </div>
                    </div>
                </dialog>
            </form>
            @once
                <script>
                (() => {
                    // ADM-03: confirm only once both checks are ticked; closing resets them.
                    document.querySelectorAll('[data-paid-form]').forEach((form) => {
                        const dialog = form.querySelector('[data-paid-dialog]');
                        const checks = [...form.querySelectorAll('[data-paid-check]')];
                        const submit = form.querySelector('[data-paid-submit]');
                        const sync = () => { submit.disabled = !checks.every((check) => check.checked); };
                        checks.forEach((check) => check.addEventListener('change', sync));
                        form.querySelector('[data-paid-open]').addEventListener('click', () => {
                            if (!form.querySelector('[data-paid-amount]').reportValidity()) return;
                            const method = form.querySelector('[data-paid-method]');
                            form.querySelector('[data-paid-summary]').textContent = `Rp ${form.querySelector('[data-paid-amount]').value} · ${method.selectedOptions[0]?.textContent ?? ''}`;
                            dialog.showModal();
                            checks[0].focus();
                        });
                        form.querySelector('[data-paid-cancel]').addEventListener('click', () => dialog.close());
                        dialog.addEventListener('close', () => { if (!form.dataset.submitting) { checks.forEach((check) => { check.checked = false; }); sync(); } });
                    });
                })();
                </script>
            @endonce
        @endif
    @endif

</details>
