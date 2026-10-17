{{-- ORD-08: what the courier is paid and whose money pays it, entered once when the courier is booked. --}}
@php
    $currentPayer = match (true) {
        $courierCost === null => $order->shipping_payer === 'cash_to_driver' ? 'customer' : null,
        $courierCost->advanced_by_name !== null => 'staff:'.$courierCost->advanced_by_name,
        ($courierCost->funding[0]['source'] ?? null) === 'cashier_cash' => 'cashier',
        default => 'customer',
    };
    $selectedPayer = old('cost_payer', $currentPayer);
    $amountValue = old('cost_amount', $courierCost ? (int) $courierCost->amount : ($order->shipping_fee !== null && (int) $order->shipping_fee > 0 ? (int) $order->shipping_fee : null));
@endphp
<label class="block text-sm font-medium text-gray-700">{{ $amountLabel ?? 'Ongkir ke kurir (Rp)' }}
    <input name="cost_amount" inputmode="numeric" required value="{{ $amountValue }}" placeholder="mis. 15000" class="{{ $input }}">
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
    <p class="mt-1 text-xs text-gray-600">Uang karyawan otomatis jadi reimburse yang sudah disetujui di Qammaris App.</p>
</fieldset>
