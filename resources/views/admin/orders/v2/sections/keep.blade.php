{{-- Keep (collapsed unless a keep is running). --}}
@php($keepLabel = ['active' => 'Aktif sampai '.$time($order->keep_until), 'expired' => 'Lewat batas', 'converted' => 'Jadi pesanan', 'released' => 'Dilepas'][$keep] ?? 'Belum dipakai')
<details id="keep" class="{{ $card }}" @if ($isOpen('keep', in_array($keep, ['active', 'expired'], true))) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2">
        <span><span class="block text-base font-semibold text-gray-900">Keep</span><span @class(['block text-sm', 'text-red-700' => $keep === 'expired', 'text-gray-600' => $keep !== 'expired'])>{{ $keepLabel }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'keep'])
    @if (in_array($keep, ['active', 'expired'], true))
        @if ($keep === 'expired')<p class="mt-2 text-sm text-red-800">Lewat batas. Hubungi customer, lalu perpanjang atau lepas.</p>@endif
        @if ($order->keep_stock_confirmed_at)
            <p class="mt-2 text-sm text-green-800">Stok sudah dipisahkan {{ $time($order->keep_stock_confirmed_at) }}.</p>
        @else
            <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'stock']) }}" class="mt-3">
                @csrf {!! $hidden('keep') !!}
                <button class="{{ $primary }}" data-busy-label="Menyimpan…">Stok sudah saya pisahkan</button>
            </form>
        @endif
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'extend']) }}" class="flex items-end gap-2">
                @csrf {!! $hidden('keep') !!}
                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Perpanjang
                    <select name="hours" class="{{ $input }}">@foreach ([6, 12, 24, 48] as $hours)<option value="{{ $hours }}" @selected($hours === 24)>{{ $hours }} jam</option>@endforeach</select>
                </label>
                <button class="{{ $secondary }}" data-busy-label="…">Perpanjang</button>
            </form>
            <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'release']) }}" class="flex items-end gap-2" data-confirm="Lepas keep? Stok boleh dijual lagi.">
                @csrf {!! $hidden('keep') !!}
                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Alasan lepas<input name="reason" required minlength="5" maxlength="200" class="{{ $input }}"></label>
                <button class="{{ $secondary }}" data-busy-label="…">Lepas</button>
            </form>
        </div>
    @elseif ($open && $pending && $order->payment_status === 'unpaid')
        <form method="POST" action="{{ route('admin.orders.v2.keep', [$order, 'start']) }}" class="mt-3 flex flex-wrap items-end gap-2">
            @csrf {!! $hidden('keep') !!}
            <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Simpan barang selama
                <select name="hours" class="{{ $input }}">@foreach ([6, 12, 24, 48, 72] as $hours)<option value="{{ $hours }}" @selected($hours === 24)>{{ $hours }} jam</option>@endforeach</select>
            </label>
            <button class="{{ $secondary }}" data-busy-label="Menyimpan…">Mulai keep</button>
        </form>
    @else
        <p class="mt-2 text-sm text-gray-600">{{ $keep === 'converted' ? 'Keep selesai karena pesanan sudah lunas atau diserahkan.' : 'Keep hanya untuk pesanan yang belum dibayar dan belum diserahkan.' }}</p>
    @endif
</details>
