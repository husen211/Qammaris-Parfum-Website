@use('App\Models\OnlineOrder')
@use('App\Support\OnlineOrderLabels')
{{-- ORD-03 packing: one action. Each line is ticked at its ordered quantity; the server still requires the exact quantities. --}}
<details id="packing" class="{{ $card }}" @if ($openSection) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900">Packing</span><span class="block text-sm text-gray-600">{{ OnlineOrderLabels::PREPARATION[$order->preparation_status] }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'packing'])
    @if ($order->preparation_status === 'packed')
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($order->items as $item)<li class="flex justify-between gap-3"><span>{{ $item->label() }}</span><span class="font-semibold">✓ {{ $item->quantity }}</span></li>@endforeach
        </ul>
    @elseif (! $active)
        <p class="mt-3 text-sm text-gray-600">{{ match ($order->lifecycle) { 'draft' => 'Bisa dikerjakan setelah pesanan website dikonfirmasi.', 'awaiting_customer' => 'Bisa dikerjakan setelah data customer lengkap.', default => 'Pesanan sudah ditutup.' } }}</p>
    @else
        <form method="POST" action="{{ route('admin.orders.v2.pack', $order) }}" class="mt-3 space-y-2" data-pack-form>
            @csrf {!! $hidden('packing') !!}
            <fieldset>
                <legend class="text-sm text-gray-700">Centang barang yang sudah masuk paket</legend>
                <div class="mt-2 space-y-2">
                    @foreach ($order->items as $item)
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-gray-300 px-3 text-sm has-[:checked]:border-black has-[:checked]:bg-gray-50">
                            <input type="checkbox" name="packed[{{ $item->line_id }}]" value="{{ $item->quantity }}" class="h-5 w-5 shrink-0" data-pack-line>
                            <span class="min-w-0 flex-1"><span class="font-semibold">{{ $item->quantity }}×</span> {{ $item->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button class="{{ $primary }}" data-busy-label="Mengonfirmasi…" data-pack-submit>Konfirmasi packing</button>
            <p class="text-xs text-gray-500">Ada yang kurang? Laporkan lewat <a href="#kendala" class="underline underline-offset-2" data-open-section="kendala">Ada masalah</a>.</p>
        </form>
    @endif
</details>
