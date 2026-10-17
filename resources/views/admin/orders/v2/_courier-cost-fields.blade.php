{{-- ORD-08: what the courier is paid and whose money pays it, entered once when the courier is booked. --}}
@php
    $currentPayer = $courierCost === null
        ? ($order->shipping_payer === 'cash_to_driver' ? 'customer' : null)
        : \App\Actions\Orders\OnlineOrderFulfillment::courierCostPayer($courierCost);
    $selectedPayer = old('cost_payer', $currentPayer);
    $amountValue = old('cost_amount', $courierCost ? (int) $courierCost->amount : ($order->shipping_fee !== null && (int) $order->shipping_fee > 0 ? (int) $order->shipping_fee : null));
@endphp
<label class="block text-sm font-medium text-gray-700">{{ $amountLabel ?? 'Ongkir ke kurir (Rp)' }}
    <input name="cost_amount" data-rupiah-input inputmode="numeric" required value="{{ $amountValue !== null && $amountValue !== '' ? (is_numeric($amountValue) ? number_format((int) $amountValue, 0, ',', '.') : $amountValue) : '' }}" placeholder="mis. 15.000" class="{{ $input }}">
</label>
<fieldset class="mt-3">
    <legend class="text-sm font-medium text-gray-700">Uang siapa?</legend>
    <div class="mt-1 grid grid-cols-2 gap-2">
        @foreach ($courierCostPayers as $value => $label)
            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-800 has-[:checked]:border-black has-[:checked]:bg-gray-900 has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-black/30">
                <input type="radio" name="cost_payer" value="{{ $value }}" required @checked($selectedPayer === $value) class="size-4 accent-current">
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @if ($courierCostByOwner)
        <p class="mt-1 text-xs text-gray-600">Uang saya = dibayar Owner (mis. GoPay), tidak menjadi reimburse.</p>
    @else
        <p class="mt-1 text-xs text-gray-600">Nama karyawan diambil dari Qammaris App. Uang karyawan otomatis jadi reimburse yang sudah disetujui.</p>
        @if ($employeesStale)
            <p class="mt-1 text-xs text-amber-800" data-employees-stale>Daftar karyawan dari Qammaris App belum bisa dimuat ulang; memakai daftar terakhir. Muat ulang halaman sebentar lagi.</p>
        @endif
    @endif
</fieldset>
