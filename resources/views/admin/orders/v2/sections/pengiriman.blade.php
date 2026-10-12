@use('App\Models\OnlineOrder')
@use('App\Support\OnlineOrderLabels')
{{-- ORD-03 shipping: one primary action per state. J&T: request pickup -> waiting for the courier -> picked up; QR and tracking number optional. --}}
@php
    $jntWaiting = in_array($order->jnt_status, ['pickup_requested', 'qr_available'], true);
    $shippingSummary = match (true) {
        ! $order->fulfillment => 'Cara menerima belum diisi',
        ! $pending && $order->delivery_status === 'delivered' => 'Diterima customer',
        ! $pending => 'Diserahkan ke '.(OnlineOrderLabels::HANDED_TO[$order->handed_to] ?? '-'),
        $order->fulfillment === 'intercity' && $jntWaiting => 'Menunggu kurir J&T',
        $order->fulfillment === 'intercity' => 'Pickup J&T belum diminta',
        $order->fulfillment === 'pickup' => 'Menunggu diambil customer',
        default => 'Belum diserahkan',
    };
    $notPacked = $order->preparation_status !== 'packed';
@endphp
<details id="pengiriman" class="{{ $card }}" @if ($openSection) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2" @if ($openSection) data-next-step @endif>
        <span><span class="block text-lg font-semibold text-gray-900">{{ OnlineOrder::FULFILLMENTS[$order->fulfillment] ?? 'Pengiriman' }}</span><span class="block text-sm text-gray-600">{{ $shippingSummary }}</span></span>
        <span aria-hidden="true" class="text-gray-400">▾</span>
    </summary>
    @include('admin.orders.v2._notice', ['section' => 'pengiriman'])

    @if (! $order->fulfillment)
        <p class="mt-3 text-sm text-gray-600">Isi cara menerima di Data penerima.</p>
    @elseif (! $pending)
        <p class="mt-3 text-sm text-gray-700">Diserahkan {{ $time($order->handed_over_at) }}.@if ($order->tracking_number) Resi <span class="font-mono">{{ $order->tracking_number }}</span>.@endif</p>
        @if ($order->fulfillment === 'intercity' && ! $order->tracking_number && $order->lifecycle !== 'cancelled')
            <form method="POST" action="{{ route('admin.orders.v2.jnt', $order) }}" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf {!! $hidden('pengiriman') !!}
                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Nomor resi <span class="font-normal text-gray-500">(opsional)</span>
                    <input name="tracking_number" maxlength="40" autocapitalize="characters" class="{{ $input }} font-mono">
                </label>
                <button class="{{ $secondary }}" data-busy-label="Menyimpan…">Simpan resi</button>
            </form>
        @endif
        @if ($order->fulfillment !== 'pickup' && $order->delivery_status !== 'delivered' && $order->lifecycle !== 'cancelled')
            <form method="POST" action="{{ route('admin.orders.v2.delivery', $order) }}" class="mt-3">
                @csrf {!! $hidden('pengiriman') !!}
                <button class="{{ $secondary }} w-full sm:w-auto" data-busy-label="Menyimpan…">Tandai diterima customer</button>
            </form>
        @endif
    @elseif ($order->fulfillment === 'pickup')
        @if ($active)
            <form method="POST" action="{{ route('admin.orders.v2.handover', $order) }}" class="mt-3" data-confirm="Customer sudah mengambil pesanan?">
                @csrf {!! $hidden('pengiriman') !!} <input type="hidden" name="handed_to" value="customer">
                <button class="{{ $primary }}" @disabled($notPacked) data-busy-label="Menyimpan…">Sudah diambil customer</button>
                @if ($notPacked)<p class="mt-1 text-xs text-gray-500">Konfirmasi packing dulu.</p>@endif
            </form>
        @endif
    @elseif ($order->fulfillment === 'local_delivery')
        @if ($active)
            <form method="POST" action="{{ route('admin.orders.v2.courier-responsibility', $order) }}" class="mt-3">
                @csrf {!! $hidden('pengiriman') !!}
                <fieldset>
                    <legend class="text-sm text-gray-700">Kurir dipesan oleh</legend>
                    <div class="mt-1 grid grid-cols-2 gap-2">
                        @foreach (['store' => 'Toko', 'customer' => 'Customer'] as $value => $label)
                            @php($selected = $order->courier_booking_responsibility === $value)
                            <button name="responsibility" value="{{ $value }}" aria-pressed="{{ $selected ? 'true' : 'false' }}" @class([
                                'inline-flex min-h-11 items-center justify-center rounded-lg border px-4 text-sm font-semibold',
                                'border-black bg-gray-900 text-white' => $selected,
                                'border-gray-300 bg-white text-gray-800 hover:bg-gray-50' => ! $selected,
                            ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </fieldset>
            </form>
            @if ($order->courier_booking_responsibility === 'store' && $order->courier_status !== 'arrived')
                <form method="POST" action="{{ route('admin.orders.v2.courier', $order) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf {!! $hidden('pengiriman') !!}
                    <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Kurir
                        <select name="provider" required class="{{ $input }}">@foreach (OnlineOrderLabels::PROVIDERS as $value => $label)<option value="{{ $value }}" @selected(old('provider', $order->courier_provider) === $value)>{{ $label }}</option>@endforeach</select>
                    </label>
                    @if ($order->courier_status === 'unassigned')
                        <button name="status" value="requested" class="{{ $secondary }}" data-busy-label="Menyimpan…">Sudah dipesan</button>
                    @else
                        <button name="status" value="arrived" class="{{ $secondary }}" data-busy-label="Menyimpan…">Kurir tiba</button>
                    @endif
                </form>
                @if ($order->courier_status === 'requested')<p class="mt-1 text-xs text-gray-600">Kurir {{ OnlineOrderLabels::PROVIDERS[$order->courier_provider] ?? '' }} sudah dipesan.</p>@endif
            @endif
            <form method="POST" action="{{ route('admin.orders.v2.handover', $order) }}" class="mt-4" data-confirm="Pesanan sudah diserahkan ke kurir?">
                @csrf {!! $hidden('pengiriman') !!}
                <input type="hidden" name="handed_to" value="{{ $order->courier_booking_responsibility === 'customer' ? 'customer_courier' : 'courier' }}">
                <button class="{{ $primary }}" @disabled($notPacked) data-busy-label="Menyimpan…">Sudah diserahkan ke kurir</button>
                @if ($notPacked)<p class="mt-1 text-xs text-gray-500">Konfirmasi packing dulu.</p>@endif
            </form>
        @endif
    @elseif ($order->fulfillment === 'intercity' && $active)
        <form method="POST" action="{{ route('admin.orders.v2.jnt', $order) }}" class="mt-3" @if ($jntWaiting) data-confirm="Paket sudah diambil J&T? Ini sekaligus mencatat penyerahan." @endif>
            @csrf {!! $hidden('pengiriman') !!}
            @if ($jntWaiting)
                <p class="mb-2 text-sm text-gray-700">Pickup sudah diminta. Serahkan paket saat kurir datang.</p>
                <button name="status" value="picked_up" class="{{ $primary }}" @disabled($notPacked) data-busy-label="Menyimpan…">Sudah di-pickup J&T</button>
                @if ($notPacked)<p class="mt-1 text-xs text-gray-500">Konfirmasi packing dulu.</p>@endif
            @else
                <button name="status" value="pickup_requested" class="{{ $primary }}" data-busy-label="Menyimpan…">Request pickup J&T</button>
                <p class="mt-1 text-xs text-gray-500">Jam layanan J&T 09.00–16.00 WITA.</p>
            @endif
        </form>
        <details class="mt-3 border-t border-gray-100 pt-2" @if (old('_section') === 'pengiriman') open @endif>
            <summary class="{{ $quiet }} cursor-pointer">Opsi lanjutan: QR & resi</summary>
            <form method="POST" action="{{ route('admin.orders.v2.jnt.qr', $order) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf {!! $hidden('pengiriman') !!}
                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Foto QR J&T <span class="font-normal text-gray-500">(opsional)</span>
                    <input type="file" name="qr" accept="image/png,image/jpeg" required class="mt-1 block w-full text-sm">
                </label>
                <button class="{{ $secondary }}" data-busy-label="Mengunggah…">Unggah QR</button>
            </form>
            @if ($order->jnt_qr_path)<a href="{{ route('admin.orders.v2.jnt.qr.show', $order) }}" target="_blank" rel="noopener" class="mt-1 inline-flex min-h-11 items-center text-sm underline underline-offset-4">Lihat QR</a>@endif
            <form method="POST" action="{{ route('admin.orders.v2.jnt', $order) }}" class="mt-2 flex flex-wrap items-end gap-2">
                @csrf {!! $hidden('pengiriman') !!}
                <label class="min-w-0 flex-1 text-sm font-medium text-gray-700">Nomor resi <span class="font-normal text-gray-500">(opsional)</span>
                    <input name="tracking_number" maxlength="40" value="{{ old('tracking_number', $order->tracking_number) }}" autocapitalize="characters" class="{{ $input }} font-mono">
                </label>
                <button class="{{ $secondary }}" data-busy-label="Menyimpan…">Simpan resi</button>
            </form>
        </details>
    @endif
</details>
