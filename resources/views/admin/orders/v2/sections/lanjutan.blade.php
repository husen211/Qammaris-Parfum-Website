{{-- ORD-03 "Opsi lanjutan": price adjustments (applied directly since ADM-01; older pending ones wait for Super Admin) and cancelling. --}}
@php($pendingAdjustments = $order->adjustments->where('status', 'pending')->count())
<details id="lanjutan" class="{{ $card }}" @if ($isOpen('harga') || $isOpen('batal') || ($pendingAdjustments > 0 && auth()->user()->can('orders.approve-adjustment'))) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
        <span><span class="block text-base font-semibold text-gray-900">Opsi lanjutan</span><span class="block text-sm text-gray-600">{{ $pendingAdjustments > 0 ? $pendingAdjustments.' penyesuaian harga menunggu persetujuan' : 'Penyesuaian harga, poin member, batalkan pesanan' }}</span></span>
        @include('admin.orders.v2._chevron')
    </summary>

    <section id="harga" class="mt-3 scroll-mt-20" aria-labelledby="harga-title">
        <h3 id="harga-title" class="text-sm font-semibold text-gray-900">Penyesuaian harga</h3>
        @include('admin.orders.v2._notice', ['section' => 'harga'])
        @foreach ($order->adjustments as $adjustment)
            <div class="mt-2 rounded-lg border border-gray-200 p-3 text-sm">
                <p class="flex justify-between gap-2"><span class="font-medium">{{ $adjustment->reason }}</span><span class="shrink-0 font-semibold tabular-nums">{{ format_rupiah($adjustment->amount) }}</span></p>
                <p class="mt-1 text-xs text-gray-500">{{ ['pending' => 'Menunggu persetujuan Super Admin', 'approved' => 'Diterapkan', 'rejected' => 'Ditolak'][$adjustment->status] }}{{ $adjustment->decision_note ? ' · '.$adjustment->decision_note : '' }}</p>
                @if ($adjustment->status === 'pending')
                    @can('orders.approve-adjustment')
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <form method="POST" action="{{ route('admin.orders.v2.adjustments.decide', [$order, $adjustment->id, 'approve']) }}" data-confirm="Setujui perubahan harga ini?">
                                @csrf {!! $hidden('harga') !!}<button class="{{ $secondary }} w-full" data-busy-label="…">Setujui</button>
                            </form>
                            <form method="POST" action="{{ route('admin.orders.v2.adjustments.decide', [$order, $adjustment->id, 'reject']) }}" class="flex gap-2">
                                @csrf {!! $hidden('harga') !!}<input name="note" required minlength="5" maxlength="300" placeholder="Alasan tolak" aria-label="Alasan menolak" class="min-h-11 min-w-0 flex-1 rounded-lg border border-gray-300 px-3 text-base"><button class="{{ $secondary }}" data-busy-label="…">Tolak</button>
                            </form>
                        </div>
                    @endcan
                @endif
            </div>
        @endforeach
        @if ($open)
            {{-- ADM-04: "Poin member" is a fixed rupiah discount from the customer's points and needs no reason. --}}
            <form method="POST" action="{{ route('admin.orders.v2.adjustments', $order) }}" class="mt-2 grid gap-3 sm:grid-cols-3" data-confirm="Terapkan penyesuaian harga ini? Total pesanan langsung berubah." data-adjustment-form>
                @csrf {!! $hidden('harga') !!}
                <label class="text-sm font-medium text-gray-700">Jenis
                    <select name="direction" class="{{ $input }}" data-adjustment-direction>
                        @foreach (['discount' => 'Potongan', 'member_points' => 'Poin member', 'surcharge' => 'Tambahan'] as $value => $label)<option value="{{ $value }}" @selected(old('direction') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700">Nominal (Rp)<input name="amount" data-rupiah-input inputmode="numeric" required value="{{ old('amount') }}" class="{{ $input }}"></label>
                <label class="text-sm font-medium text-gray-700" data-adjustment-reason @if (old('direction') === 'member_points') hidden @endif>Alasan<input name="reason" @if (old('direction') !== 'member_points') required @endif minlength="5" maxlength="300" value="{{ old('reason') }}" class="{{ $input }}"></label>
                <div class="sm:col-span-3"><button class="{{ $primary }}" data-busy-label="Menerapkan…">Terapkan penyesuaian</button>
                    <p class="mt-1 text-xs text-gray-500">Total langsung berubah dan tercatat di Riwayat.</p></div>
            </form>
            <script>
            // ADM-04: points need no reason; the field hides and stops being required.
            document.querySelectorAll('[data-adjustment-form]').forEach((form) => {
                const direction = form.querySelector('[data-adjustment-direction]');
                const reason = form.querySelector('[data-adjustment-reason]');
                const sync = () => { const points = direction.value === 'member_points'; reason.hidden = points; reason.querySelector('input').required = !points; };
                direction.addEventListener('change', sync);
                sync();
            });
            </script>
        @endif
    </section>

    @if ($open)
        <section id="batal" class="mt-5 scroll-mt-20 border-t border-gray-100 pt-4" aria-labelledby="batal-title">
            <h3 id="batal-title" class="text-sm font-semibold text-gray-900">Batalkan pesanan</h3>
            @include('admin.orders.v2._notice', ['section' => 'batal'])
            @if ($received > 0 && ! auth()->user()->can('orders.refund'))
                <p class="mt-1 text-sm text-gray-600">Sudah ada pembayaran: hanya Super Admin yang dapat membatalkan.</p>
            @elseif (! auth()->user()->can('orders.cancel', $order))
                <p class="mt-1 text-sm text-gray-600">Pesanan yang sudah dibayar atau diserahkan hanya dapat dibatalkan Super Admin.</p>
            @else
                <form method="POST" action="{{ route('admin.orders.v2.cancel', $order) }}" class="mt-2 space-y-3" data-confirm="Batalkan pesanan {{ $order->code }}?">
                    @csrf {!! $hidden('batal') !!}
                    <label class="block text-sm font-medium text-gray-700">Alasan<input name="reason" required minlength="5" maxlength="200" value="{{ old('reason') }}" class="{{ $input }}"></label>
                    @if ($received > 0)
                        <label class="block text-sm font-medium text-gray-700">Dikembalikan ke customer (Rp)
                            <input name="refund_due" data-rupiah-input required inputmode="numeric" value="{{ old('refund_due') }}" placeholder="0 sampai {{ (int) ($received / 100) }}" class="{{ $input }}">
                        </label>
                        <p class="text-xs text-gray-600">Sudah diterima {{ $rp($received) }}. Tulis 0 bila tidak dikembalikan.</p>
                    @endif
                    <button class="{{ $danger }}" data-busy-label="Membatalkan…">Ya, batalkan pesanan</button>
                </form>
            @endif
        </section>
    @endif
</details>
