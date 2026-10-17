{{-- ORD-08: the recorded courier fee, its reimbursement in Qammaris App, and the correction form. --}}
@php
    $reimbursementLabel = $courierCost ? match ($courierCost->reimbursement_status) {
        'submitted', 'awaiting_proof' => 'reimburse sedang dikirim ke Qammaris App',
        'approved' => 'reimburse disetujui',
        'paid' => 'reimburse sudah dibayar',
        'rejected' => 'reimburse ditolak Owner',
        'cancelled' => 'reimburse dibatalkan',
        default => null,
    } : null;
    // ORD-08: the employee's current App name (by account ID), not the name at the time of booking.
    $payerLabel = $courierCost ? ($courierCost->advanced_by_name !== null ? 'uang '.\App\Support\OrderApi\AppEmployees::nameOf($courierCost->advanced_by_app_user_id, $courierCost->advanced_by_name)
        : match ($courierCost->funding[0]['source'] ?? null) { 'owner_fund' => 'uang Owner (GoPay)', 'cashier_cash' => 'uang kasir', default => 'dibayar customer ke driver' }) : null;
    $locked = $courierCost?->reimbursement_status === 'paid';
@endphp
<div class="mt-4 border-t border-gray-100 pt-3" data-courier-cost>
    @if ($courierCost)
        <p class="text-sm text-gray-800"><span class="font-semibold">Ongkir kurir {{ format_rupiah($courierCost->amount) }}</span> · {{ $payerLabel }}@if ($reimbursementLabel) · <span data-reimbursement-status="{{ $courierCost->reimbursement_status }}">{{ $reimbursementLabel }}</span>@endif</p>
        @if ($locked)
            <p class="mt-1 text-xs text-gray-600">Reimburse sudah dibayar. Koreksi lewat Owner di Qammaris App.</p>
        @else
            <details class="mt-1" @if (old('_section') === 'pengiriman' && old('cost_payer') !== null) open @endif>
                <summary class="{{ $quiet }} -ml-3 cursor-pointer">Ubah ongkir kurir</summary>
                <form method="POST" action="{{ route('admin.orders.v2.courier-cost', $order) }}" class="mt-2">
                    @csrf {!! $hidden('pengiriman') !!}
                    @include('admin.orders.v2._courier-cost-fields')
                    <button class="{{ $secondary }} mt-3" data-busy-label="Menyimpan…">Simpan ongkir kurir</button>
                </form>
            </details>
        @endif
    @else
        <form method="POST" action="{{ route('admin.orders.v2.courier-cost', $order) }}">
            @csrf {!! $hidden('pengiriman') !!}
            <p class="mb-2 text-sm font-semibold text-gray-900">Catat ongkir kurir</p>
            @include('admin.orders.v2._courier-cost-fields')
            <button class="{{ $secondary }} mt-3" data-busy-label="Menyimpan…">Simpan ongkir kurir</button>
        </form>
    @endif
</div>
